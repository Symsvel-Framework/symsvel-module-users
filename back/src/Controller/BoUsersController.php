<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Acls\Access\BoAccessGate;
use SymSvel\Module\Auth\Identity\EmailVerificationDispatch;
use SymSvel\Module\Auth\Identity\AppInvitationDispatch;
use SymSvel\Module\Users\Service\UserAdministration;

/** All actions use BO tokens and explicit ACLs; never App credentials. */
final readonly class BoUsersController
{
    public function __construct(
        private BoAccessGate $access,
        private Connection $connection,
        private UserAdministration $users,
        private EmailVerificationDispatch $confirmation,
        private AppInvitationDispatch $invitations,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/apibo/users', name: 'symsvel_users_bo_list', methods: ['GET'], format: 'json')]
    public function list(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'list')) !== null) {
            return $error;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, email, enabled, email_verified_at, created_at FROM symsvel_users ORDER BY id DESC LIMIT 200',
        );
        return self::reply(['data' => array_map(static fn (array $row) => [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'enabled' => (bool) $row['enabled'],
            'email_verified' => $row['email_verified_at'] !== null,
            'created_at' => (string) $row['created_at'],
        ], $rows)]);
    }

    #[Route('/apibo/users', name: 'symsvel_users_bo_create', methods: ['POST'], format: 'json')]
    public function create(Request $request): JsonResponse
    {
        if (($error = $this->authorize($request, 'create')) !== null) {
            return $error;
        }
        $body = self::payload($request);
        if ($body instanceof JsonResponse) {
            return $body;
        }
        if (array_diff(array_keys($body), ['email', 'locale'])
            || !is_string($body['email'] ?? null)
            || !in_array($body['locale'] ?? 'fr', ['fr', 'en'], true)) {
            return self::error(400);
        }
        try {
            $id = $this->users->invite(
                $body['email'], new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException) {
            return self::error(409);
        }
        $sent = $this->invite($id, $body['email'], $body['locale'] ?? 'fr');
        return self::reply(['id' => $id, 'status' => 'invited', 'invitation_sent' => $sent], 201);
    }

    #[Route('/apibo/users/{id<\d+>}/invitations/resend', name: 'symsvel_users_bo_invitation_resend', methods: ['POST'], format: 'json')]
    public function resend(Request $request, int $id): JsonResponse
    {
        if (($error = $this->authorize($request, 'update')) !== null) return $error;
        $body = self::payload($request);
        if ($body instanceof JsonResponse) return $body;
        if (array_diff(array_keys($body), ['locale'])
            || !in_array($body['locale'] ?? 'fr', ['fr', 'en'], true)) {
            return self::error(400);
        }
        $email = $this->users->pendingInvitationEmail($id);
        if ($email === null) return self::error(404);
        $sent = $this->invite($id, $email, $body['locale'] ?? 'fr');
        return self::reply(['status' => $sent ? 'invitation_resent' : 'mail_unavailable', 'invitation_sent' => $sent]);
    }

    #[Route('/apibo/users/{id<\d+>}', name: 'symsvel_users_bo_update', methods: ['PATCH'], format: 'json')]
    public function update(Request $request, int $id): JsonResponse
    {
        if (($error = $this->authorize($request, 'update')) !== null) {
            return $error;
        }
        $body = self::payload($request);
        if ($body instanceof JsonResponse) {
            return $body;
        }
        if ($body === [] || array_diff(array_keys($body), ['email', 'enabled', 'locale'])
            || (array_key_exists('email', $body) && !is_string($body['email']))
            || (array_key_exists('enabled', $body) && !is_bool($body['enabled']))
            || !in_array($body['locale'] ?? 'fr', ['fr', 'en'], true)
            || (!array_key_exists('email', $body) && !array_key_exists('enabled', $body))) {
            return self::error(400);
        }
        try {
            $pendingEmail = $this->users->update($id, $body['email'] ?? null, $body['enabled'] ?? null);
        } catch (\InvalidArgumentException) {
            return self::error(400);
        } catch (\DomainException) {
            return self::error(409);
        }
        $sent = $pendingEmail !== null ? $this->deliver($pendingEmail, $body['locale'] ?? 'fr', $request) : null;
        return self::reply(['status' => 'updated', 'confirmation_sent' => $sent]);
    }

    #[Route('/apibo/users/{id<\d+>}', name: 'symsvel_users_bo_delete', methods: ['DELETE'], format: 'json')]
    public function delete(Request $request, int $id): JsonResponse
    {
        if (($error = $this->authorize($request, 'delete')) !== null) {
            return $error;
        }
        try {
            $this->users->delete($id);
        } catch (\DomainException) {
            return self::error(404);
        }
        return self::reply(['status' => 'deleted']);
    }

    private function invite(int $id, string $email, string $locale): bool
    {
        try {
            $this->invitations->dispatch(
                $id, strtolower(trim($email)), $locale,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
            return true;
        } catch (\Exception $exception) {
            $this->logger->error('BO user invitation delivery failed.', ['exception' => $exception]);
            return false;
        }
    }

    private function deliver(string $email, string $locale, Request $request): bool
    {
        try {
            return $this->confirmation->dispatch(
                'app', strtolower(trim($email)), $locale, (string) $request->getClientIp(),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
        } catch (\Exception $exception) {
            $this->logger->error('BO user verification delivery failed.', ['exception' => $exception]);
            return false;
        }
    }

    private function authorize(Request $request, string $action): ?JsonResponse
    {
        $decision = $this->access->check($request, 'users', $action);
        return $decision->allowed() ? null : self::error($decision->status);
    }

    /** @return array<string, mixed>|JsonResponse */
    private static function payload(Request $request): array|JsonResponse
    {
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')) {
            return self::error(415);
        }
        $raw = $request->getContent();
        if (strlen($raw) > 8192) {
            return self::error(400);
        }
        try {
            $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::error(400);
        }
        return is_array($body) && !array_is_list($body) ? $body : self::error(400);
    }

    private static function error(int $status): JsonResponse
    {
        return self::reply(['error' => ['code' => match ($status) {
            400 => 'invalid_request', 401 => 'unauthorized', 403 => 'forbidden',
            404 => 'not_found', 409 => 'conflict', 415 => 'unsupported_media_type',
            default => 'unavailable',
        }]], $status);
    }

    private static function reply(array $body, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($body, $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }
}
