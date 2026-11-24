<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Controller;

use SymSvel\Module\Auth\Identity\NewPasswordPolicy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Auth\Identity\ProfileEmailChangeDispatch;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;
use SymSvel\Module\Languages\Service\LanguageCatalog;
use SymSvel\Module\Users\Entity\User;

/** Self-service profile, bound to the authenticated App token. */
final readonly class UserProfileController
{
    public function __construct(
        private AuthenticatedTokenResolver $tokens,
        private EntityManagerInterface $entityManager,
        private LanguageCatalog $languages,
        private Connection $db,
        private TokenSessionRevoker $sessionRevoker,
        private ProfileEmailChangeDispatch $emailChange,
    ) {
    }

    #[Route('/api/users/profile', name: 'symsvel_users_self_profile', methods: ['GET'], format: 'json')]
    public function show(Request $request): JsonResponse
    {
        $user = $this->self($request);
        return $user === null ? self::failure(401) : self::reply(['data' => self::present($user)]);
    }

    #[Route('/api/users/profile', name: 'symsvel_users_self_profile_update', methods: ['PATCH'], format: 'json')]
    public function update(Request $request): JsonResponse
    {
        $user = $this->self($request);
        if ($user === null) return self::failure(401);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 1024) return self::failure(400);
        try {
            $body = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        // Never accept a target ID, verified flag, password, email or account status.
        if (!is_array($body) || array_is_list($body)
            || array_keys($body) !== ['language'] || !is_string($body['language'])) {
            return self::failure(400);
        }
        try {
            $language = $this->languages->resolveAccountLanguage($body['language']);
            $user->setLanguage($language);
        } catch (\InvalidArgumentException) {
            return self::failure(400);
        } catch (\DomainException) {
            return self::failure(409);
        }
        $this->entityManager->flush();

        return self::reply(['data' => self::present($user)]);
    }


    /**
     * Self-service password change. Context and account identity come exclusively
     * from the verified Bearer; no role or account-management ACL is required.
     */
    #[Route('/api/users/profile/password', name: 'symsvel_users_profile_password', methods: ['POST'], format: 'json')]
    public function changePassword(Request $request): JsonResponse
    {
        $account = $this->self($request);
        if ($account === null) return self::failure(401);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 4096) return self::failure(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        if (!is_array($data) || array_is_list($data) || count($data) !== 2
            || !is_string($data['current_password'] ?? null)
            || !is_string($data['new_password'] ?? null)
            || !NewPasswordPolicy::accepts($data['new_password'])
            || strlen($data['current_password']) > 4096) {
            return self::failure(400);
        }
        $oldHash = $account->getPasswordHash();
        if (!password_verify($data['current_password'], $oldHash)
            || password_verify($data['new_password'], $oldHash)) {
            return self::failure(403);
        }
        $newHash = password_hash($data['new_password'], PASSWORD_ARGON2ID);
        $subject = new TokenSubject('app', (int) $account->getId());
        try {
            $this->sessionRevoker->revokeWith(
                $subject,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                function () use ($subject, $oldHash, $newHash): void {
                    $updated = $this->db->executeStatement(
                        'UPDATE symsvel_users SET password_hash = ? WHERE id = ? AND password_hash = ? AND enabled = ?',
                        [$newHash, $subject->id, $oldHash, true],
                        [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::BOOLEAN],
                    );
                    if ($updated !== 1) {
                        throw new \DomainException('Account credentials changed concurrently.');
                    }
                },
            );
        } catch (\DomainException) {
            return self::failure(409);
        }
        return self::reply(['status' => 'updated']);
    }


    /** Stage a new address; it is not applied until the recipient confirms the token. */
    #[Route('/api/users/profile/email-change', name: 'symsvel_users_profile_email_change', methods: ['POST'], format: 'json')]
    public function requestEmailChange(Request $request): JsonResponse
    {
        $account = $this->self($request);
        if ($account === null) return self::failure(401);
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')
            || strlen($request->getContent()) > 4096) return self::failure(400);
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::failure(400);
        }
        if (!is_array($data) || array_is_list($data) || count($data) !== 3
            || !is_string($data['current_password'] ?? null)
            || strlen($data['current_password']) > 4096
            || !is_string($data['new_email'] ?? null)
            || strlen(trim($data['new_email'])) > 254
            || filter_var(trim($data['new_email']), FILTER_VALIDATE_EMAIL) === false
            || !in_array($data['locale'] ?? null, ['fr', 'en'], true)) {
            return self::failure(400);
        }
        if (!password_verify($data['current_password'], $account->getPasswordHash())) {
            return self::failure(403);
        }
        try {
            $dispatched = $this->emailChange->dispatch(
                new TokenSubject('app', (int) $account->getId()),
                $account->getEmail(),
                $data['new_email'],
                $data['locale'],
                (string) $request->getClientIp(),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
        } catch (\Exception) {
            return self::failure(503);
        }
        return $dispatched ? self::reply(['status' => 'accepted'], 202)
            : self::reply(['error' => ['code' => 'unavailable']], 429);
    }

    private function self(Request $request): ?User
    {
        if (!preg_match('/^Bearer ([A-Za-z0-9_-]{43})$/D', $request->headers->get('Authorization', ''), $found)) {
            return null;
        }
        $subject = $this->tokens->resolve(
            $found[1], 'app', new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($subject === null) return null;
        $user = $this->entityManager->find(User::class, $subject->id);
        return $user instanceof User && $user->isEnabled() ? $user : null;
    }

    /** @return array{id:int,email:string,language:?string,email_verified:bool} */
    public static function present(User $user): array
    {
        return [
            'id' => (int) $user->getId(),
            'email' => $user->getEmail(),
            'language' => $user->getLanguage(),
            'email_verified' => $user->getEmailVerifiedAt() !== null,
        ];
    }

    private static function failure(int $status): JsonResponse
    {
        return self::reply(['error' => ['code' => match ($status) {
            400 => 'invalid_request', 401 => 'unauthorized', 403 => 'invalid_credentials', 409 => 'unavailable', 503 => 'unavailable',
            default => 'unavailable',
        }]], $status);
    }

    private static function reply(array $payload, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($payload, $status);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
