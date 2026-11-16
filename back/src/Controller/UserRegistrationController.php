<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Auth\Identity\EmailVerificationDispatch;
use SymSvel\Module\Users\Service\UserRegistration;

final readonly class UserRegistrationController
{
    public function __construct(
        private UserRegistration $users,
        private EmailVerificationDispatch $verification,
    ) {
    }

    #[Route('/apiopen/users/register', name: 'symsvel_users_app_register', methods: ['POST'], format: 'json')]
    public function __invoke(Request $request): JsonResponse
    {
        if (!str_starts_with(strtolower($request->headers->get('Content-Type', '')), 'application/json')) {
            return self::reply(['error' => ['code' => 'unsupported_media_type']], 415);
        }
        $raw = $request->getContent();
        if (strlen($raw) > 8192) {
            return self::reply(['error' => ['code' => 'invalid_request']], 400);
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::reply(['error' => ['code' => 'invalid_request']], 400);
        }
        if (!is_array($data) || array_is_list($data)
            || !is_string($data['email'] ?? null) || !is_string($data['password'] ?? null)
            || !is_string($data['locale'] ?? 'fr') || !in_array($data['locale'] ?? 'fr', ['fr', 'en'], true)) {
            return self::reply(['error' => ['code' => 'invalid_request']], 400);
        }

        $email = strtolower(trim($data['email']));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        try {
            $created = $this->users->register($email, $data['password'], (string) $request->getClientIp(), $now);
        } catch (\InvalidArgumentException) {
            return self::reply(['error' => ['code' => 'invalid_request']], 400);
        }

        if ($created) {
            // Auth owns verification and e-mail delivery. This may fail after
            // persistence; the existing public resend endpoint allows recovery.
            $this->verification->dispatch(
                'app', $email, $data['locale'] ?? 'fr', (string) $request->getClientIp(), $now,
            );
        }

        // Never reveal duplicate, pending or throttled addresses.
        return self::reply(['status' => 'accepted'], 202);
    }

    private static function reply(array $payload, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($payload, $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }
}
