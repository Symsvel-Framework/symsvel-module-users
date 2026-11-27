<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use SymSvel\Module\Auth\Token\SessionIssuedListener;
use SymSvel\Module\Auth\Token\TokenSubject;

/** Users owns its activity timestamp, written in Auth's initial session transaction. */
final readonly class UserLastLoginListener implements SessionIssuedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function onSessionIssued(TokenSubject $subject, \DateTimeImmutable $at): void
    {
        if ($subject->context !== 'app') {
            return;
        }
        if (!$this->connection->isTransactionActive()) {
            throw new \LogicException('Last login requires the shared Auth transaction.');
        }

        // Initial session tokens have already been inserted, so SQLite holds
        // the writer lock. Other platforms lock the user row explicitly.
        $query = 'SELECT id FROM symsvel_users WHERE id = ? AND enabled = ? AND email_verified_at IS NOT NULL';
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $query .= ' FOR UPDATE';
        }
        $id = $this->connection->fetchOne($query, [$subject->id, true],
            [ParameterType::INTEGER, ParameterType::BOOLEAN]);
        if ($id === false) {
            throw new \DomainException('User no longer eligible for a session.');
        }

        $stamp = $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->connection->executeStatement(
            'UPDATE symsvel_users SET last_login = ? WHERE id = ? AND (last_login IS NULL OR last_login < ?)',
            [$stamp, $subject->id, $stamp],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING],
        );
    }
}
