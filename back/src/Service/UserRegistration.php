<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Service;

use SymSvel\Module\Auth\Identity\NewPasswordPolicy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SymSvel\Module\Auth\Identity\PasswordResetLimiter;

/**
 * Users owns new App identities. The public caller never returns whether an
 * address exists, is rate-limited or was just created.
 */
final readonly class UserRegistration
{
    public function __construct(
        private Connection $connection,
        private PasswordResetLimiter $limiter,
    ) {
    }

    public function register(string $email, string $password, string $clientIp, \DateTimeImmutable $now, ?string $language = null): bool
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254
            || !NewPasswordPolicy::accepts($password)) {
            throw new \InvalidArgumentException('Invalid registration data.');
        }
        if (!$this->limiter->allowRegistration($email, $clientIp, $now)) {
            return false;
        }

        try {
            $this->connection->insert('symsvel_users', [
                'email' => $email,
                'language' => $language,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                'enabled' => true,
                'created_at' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
                'email_verified_at' => null,
            ]);
            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
