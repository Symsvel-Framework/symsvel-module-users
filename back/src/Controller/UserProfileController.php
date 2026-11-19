<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Controller;

use Doctrine\ORM\EntityManagerInterface;
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
            400 => 'invalid_request', 401 => 'unauthorized', 409 => 'unavailable',
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
