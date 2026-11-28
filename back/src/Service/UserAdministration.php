<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Auth\Token\TokenSubject;

/** BO account mutations, isolated from the public registration rate limiter. */
final readonly class UserAdministration
{
    public function __construct(
        private Connection $connection,
        private TokenSessionRevoker $sessions,
    ) {
    }

    /** @throws \DomainException when the email already belongs to another user */
    public function create(string $email, string $password, \DateTimeImmutable $now, ?string $language = null): int
    {
        $email = self::email($email);
        if (strlen($password) < 12 || strlen($password) > 1024 || str_contains($password, "\0")) {
            throw new \InvalidArgumentException('Invalid user password.');
        }
        try {
            $this->connection->insert('symsvel_users', [
                'email' => $email,
                'language' => $language,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                'enabled' => true,
                'email_verified_at' => null,
                'created_at' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new \DomainException('Account already exists.', 0, $exception);
        }
        $id = $this->connection->fetchOne('SELECT id FROM symsvel_users WHERE email = ?', [$email]);
        if ($id === false) {
            throw new \LogicException('Created user cannot be found.');
        }
        return (int) $id;
    }

    /**
     * BO account invitation: the initial credential is generated with a
     * cryptographically secure random value and is never returned to the caller.
     * Only the emailed single-use activation token can set a usable password.
     */
    public function invite(string $email, \DateTimeImmutable $now, ?string $language = null): int
    {
        return $this->create($email, bin2hex(random_bytes(32)), $now, $language);
    }

    /** Pending addresses may be re-invited by an ACL-authorized BO actor. */
    public function pendingInvitationEmail(int $id): ?string
    {
        if ($id < 1) return null;
        $email = $this->connection->fetchOne(
            'SELECT email FROM symsvel_users WHERE id = ? AND enabled = ? AND email_verified_at IS NULL',
            [$id, true],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        return is_string($email) ? $email : null;
    }

    /**
     * Updates only permitted fields; returns the newly pending email, if any.
     * A changed email always loses its verification stamp and active sessions.
     */
    public function update(int $id, ?string $email, ?bool $enabled, ?string $language = null): ?string
    {
        $row = $id > 0 ? $this->connection->fetchAssociative(
            'SELECT email, enabled, language, anonymized_at FROM symsvel_users WHERE id = ?', [$id],
        ) : false;
        if ($row === false || $row['anonymized_at'] !== null) {
            throw new \DomainException('Unknown user.');
        }
        $changes = [];
        $pendingEmail = null;
        if ($email !== null) {
            $normalized = self::email($email);
            if ($normalized !== $row['email']) {
                $changes['email'] = $normalized;
                $changes['email_verified_at'] = null;
                $pendingEmail = $normalized;
            }
        }
        if ($enabled !== null && $enabled !== (bool) $row['enabled']) {
            $changes['enabled'] = $enabled;
        }
        if ($language !== null && $language !== $row['language']) {
            $changes['language'] = $language;
        }
        if ($changes === []) {
            return null;
        }
        try {
            $this->sessions->revokeWith(
                new TokenSubject('app', $id), new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                function () use ($id, $changes): void {
                    $fields = array_keys($changes);
                    $assignments = implode(', ', array_map(static fn (string $field): string => $field.' = ?', $fields));
                    $types = array_map(static fn ($value) => is_bool($value) ? \Doctrine\DBAL\ParameterType::BOOLEAN : ($value === null ? \Doctrine\DBAL\ParameterType::NULL : \Doctrine\DBAL\ParameterType::STRING), array_values($changes));
                    if ($this->connection->executeStatement('UPDATE symsvel_users SET '.$assignments.' WHERE id = ? AND anonymized_at IS NULL', [...array_values($changes), $id], [...$types, \Doctrine\DBAL\ParameterType::INTEGER]) !== 1) {
                        throw new \DomainException('User no longer editable.');
                    }
                    if (array_key_exists('email', $changes)) {
                        // Old mailbox links cannot survive an address change.
                        $this->connection->delete('symsvel_auth_email_verification_tokens', ['context' => 'app', 'principal_id' => $id]);
                        $this->connection->delete('symsvel_auth_password_reset_tokens', ['context' => 'app', 'principal_id' => $id]);
                        $this->connection->delete('symsvel_auth_app_invitations', ['principal_id' => $id]);
                    }
                },
            );
        } catch (UniqueConstraintViolationException $exception) {
            throw new \DomainException('Email already in use.', 0, $exception);
        }
        return $pendingEmail;
    }

    public function delete(int $id): void
    {
        if ($id < 1 || $this->connection->fetchOne('SELECT id FROM symsvel_users WHERE id = ? AND anonymized_at IS NULL', [$id]) === false) {
            throw new \DomainException('Unknown user.');
        }
        $this->sessions->revokeWith(
            new TokenSubject('app', $id), new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            function () use ($id): void {
                $this->connection->delete('symsvel_auth_email_verification_tokens', ['context' => 'app', 'principal_id' => $id]);
                $this->connection->delete('symsvel_auth_password_reset_tokens', ['context' => 'app', 'principal_id' => $id]);
                $this->connection->delete('symsvel_auth_app_invitations', ['principal_id' => $id]);
                if ($this->connection->executeStatement('DELETE FROM symsvel_users WHERE id = ? AND anonymized_at IS NULL', [$id]) !== 1) {
                    throw new \DomainException('User no longer deletable.');
                }
            },
        );
    }

    private static function email(string $value): string
    {
        $email = strtolower(trim($value));
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || str_ends_with($email, '@example.invalid')) {
            throw new \InvalidArgumentException('Invalid user email.');
        }
        return $email;
    }
}
