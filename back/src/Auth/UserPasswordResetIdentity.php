<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SymSvel\Module\Auth\Identity\PasswordResetIdentity;
use SymSvel\Module\Auth\Identity\EmailVerificationIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

/**
 * App-only password recovery adapter; writes through the shared DBAL connection
 * so Auth can consume the reset token, update credentials and revoke sessions atomically.
 */
final readonly class UserPasswordResetIdentity implements PasswordResetIdentity, EmailVerificationIdentity
{
    public function __construct(private Connection $connection)
    {
    }

    public function authContext(): string
    {
        return 'app';
    }

    public function findResetSubject(string $identifier): ?TokenSubject
    {
        $identifier = trim($identifier);
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) === false || strlen($identifier) > 254) {
            return null;
        }

        $id = $this->connection->fetchOne(
            'SELECT id FROM symsvel_users WHERE email = ? AND enabled = ?',
            [$identifier, true],
            [ParameterType::STRING, ParameterType::BOOLEAN],
        );

        return $id === false ? null : new TokenSubject('app', (int) $id);
    }

    /** Only active accounts with a confirmed mailbox can receive reset links. */
    public function recoveryEmail(TokenSubject $subject): ?string
    {
        if ($subject->context !== 'app') {
            return null;
        }

        $email = $this->connection->fetchOne(
            'SELECT email FROM symsvel_users WHERE id = ? AND enabled = ? AND email_verified_at IS NOT NULL',
            [$subject->id, true],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * The mailbox fingerprint is matched against the account's current email.
     * No unverified account is implicitly confirmed during bootstrap.
     */
    public function confirmEmail(TokenSubject $subject, string $emailHash, \DateTimeImmutable $now): bool
    {
        if ($subject->context !== 'app' || !preg_match('/^[a-f0-9]{64}$/D', $emailHash)) {
            return false;
        }

        $email = $this->connection->fetchOne(
            'SELECT email FROM symsvel_users WHERE id = ? AND enabled = ?',
            [$subject->id, true],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        if (!is_string($email)
            || !hash_equals(hash('sha256', strtolower(trim($email))), $emailHash)) {
            return false;
        }

        return $this->connection->executeStatement(
            'UPDATE symsvel_users SET email_verified_at = ? WHERE id = ? AND enabled = ? AND email = ? AND email_verified_at IS NULL',
            [$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'), $subject->id, true, $email],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::BOOLEAN, \Doctrine\DBAL\ParameterType::STRING],
        ) === 1;
    }

    public function replacePassword(TokenSubject $subject, string $newPasswordHash): bool
    {
        if ($subject->context !== 'app' || password_get_info($newPasswordHash)['algoName'] !== 'argon2id') {
            return false;
        }

        return $this->connection->executeStatement(
            'UPDATE symsvel_users SET password_hash = ? WHERE id = ? AND enabled = ?',
            [$newPasswordHash, $subject->id, true],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::BOOLEAN],
        ) === 1;
    }
}
