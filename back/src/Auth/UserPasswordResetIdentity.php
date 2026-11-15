<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SymSvel\Module\Auth\Identity\PasswordResetIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

/**
 * App-only password recovery adapter; writes through the shared DBAL connection
 * so Auth can consume the reset token, update credentials and revoke sessions atomically.
 */
final readonly class UserPasswordResetIdentity implements PasswordResetIdentity
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
