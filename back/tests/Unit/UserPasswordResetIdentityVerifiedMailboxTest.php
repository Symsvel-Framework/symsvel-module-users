<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Users\Auth\UserPasswordResetIdentity;
use SymSvel\Module\Users\Entity\User;
use SymSvel\Module\Auth\Token\TokenSubject;

final class UserPasswordResetIdentityVerifiedMailboxTest extends TestCase
{
    public function testVerifiedMailboxIsRequiredForPasswordResetDelivery(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY, email VARCHAR(254) NOT NULL, password_hash VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, email_verified_at DATETIME DEFAULT NULL)');
        $hash = password_hash('original-password', PASSWORD_ARGON2ID);
        $db->insert('symsvel_users', ['id' => 17, 'email' => 'confirmed@example.test', 'password_hash' => $hash, 'enabled' => true, 'email_verified_at' => null]);
        $owner = new UserPasswordResetIdentity($db);
        $subject = new TokenSubject('app', 17);

        self::assertNull((new User('new@example.test', $hash))->getEmailVerifiedAt());
        self::assertSame(17, $owner->findResetSubject('confirmed@example.test')?->id);
        self::assertNull($owner->recoveryEmail($subject));
        self::assertNull($owner->recoveryEmail(new TokenSubject('bo', 17)));
        self::assertNull($owner->recoveryEmail(new TokenSubject('app', 18)));

        // Represents the state AFTER a future verified confirmation flow.
        $db->executeStatement('UPDATE symsvel_users SET email_verified_at = ? WHERE id = ?', ['2026-11-15 12:00:00', 17]);
        self::assertSame('confirmed@example.test', $owner->recoveryEmail($subject));

        $db->executeStatement('UPDATE symsvel_users SET enabled = ? WHERE id = ?', [false, 17]);
        self::assertNull($owner->recoveryEmail($subject));
    }
}
