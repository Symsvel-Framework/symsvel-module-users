<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Auth\Identity\AppInvitationCompletion;
use SymSvel\Module\Auth\Identity\AppInvitationOwners;
use SymSvel\Module\Auth\Schema\AppInvitationSchema;
use SymSvel\Module\Auth\Token\AppInvitationToken;
use SymSvel\Module\Auth\Token\DbalAppInvitationStore;
use SymSvel\Module\Users\Auth\UserInvitationIdentity;
use SymSvel\Module\Users\Auth\UserPasswordResetIdentity;

/** Tests the actual Users adapter, not a stub of the Auth contract. */
final class UserInvitationActivationTest extends TestCase
{
    public function testOnlyCurrentPendingAccountCanActivateAndChoosePassword(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, email_verified_at DATETIME NULL, created_at DATETIME NOT NULL)');
        $schema = new Schema();
        AppInvitationSchema::apply($schema);
        foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) {
            $db->executeStatement($sql);
        }
        $db->insert('symsvel_users', [
            'email' => 'invited@example.test', 'enabled' => true, 'email_verified_at' => null,
            'password_hash' => password_hash('initial-random-unavailable', PASSWORD_ARGON2ID),
            'created_at' => '2026-11-16 14:00:00',
        ]);
        $id = (int) $db->fetchOne('SELECT id FROM symsvel_users');
        $now = new \DateTimeImmutable('2026-11-16T14:00:00Z');
        $token = AppInvitationToken::issue($now);
        $tokens = new DbalAppInvitationStore($db);
        $tokens->save($id, 'invited@example.test', $token, $now);
        $owner = new UserInvitationIdentity(new UserPasswordResetIdentity($db));
        $activation = new AppInvitationCompletion($db, $tokens, new AppInvitationOwners([$owner]));

        $db->update('symsvel_users', ['email' => 'changed@example.test'], ['id' => $id]);
        self::assertFalse($activation->complete($token->raw, 'Chosen-password-123!', $now));
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM symsvel_users WHERE id = ?', [$id]) ?: null);

        $db->update('symsvel_users', ['email' => 'invited@example.test'], ['id' => $id]);
        self::assertTrue($activation->complete($token->raw, 'Chosen-password-123!', $now));
        self::assertFalse($activation->complete($token->raw, 'Another-password-123!', $now));
        $row = $db->fetchAssociative('SELECT password_hash, email_verified_at FROM symsvel_users WHERE id = ?', [$id]);
        self::assertTrue(password_verify('Chosen-password-123!', (string) $row['password_hash']));
        self::assertNotNull($row['email_verified_at']);
    }
}
