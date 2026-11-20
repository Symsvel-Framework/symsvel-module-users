<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Users\Auth\UserPasswordResetIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

final class UserEmailVerificationTest extends TestCase
{
    public function testOnlyCurrentActiveAccountEmailCanBeConfirmedOnce(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY, email VARCHAR(254), enabled BOOLEAN, email_verified_at DATETIME NULL)');
        $db->insert('symsvel_users', [
            'id' => 7, 'email' => 'owner@example.test', 'enabled' => true, 'email_verified_at' => null,
        ]);

        $owner = new UserPasswordResetIdentity($db);
        $subject = new TokenSubject('app', 7);
        $now = new \DateTimeImmutable('2026-11-15T12:00:00+00:00');

        self::assertFalse($owner->confirmEmail(new TokenSubject('bo', 7), hash('sha256', 'owner@example.test'), $now));
        self::assertFalse($owner->confirmEmail($subject, hash('sha256', 'other@example.test'), $now));
        self::assertFalse($owner->confirmEmail($subject, 'invalid', $now));
        self::assertSame('owner@example.test', $owner->pendingVerificationEmail($subject));
        self::assertNull($owner->pendingVerificationEmail(new TokenSubject('bo', 7)));
        self::assertNull($owner->recoveryEmail($subject));

        self::assertTrue($owner->confirmEmail($subject, hash('sha256', 'owner@example.test'), $now));
        self::assertSame('owner@example.test', $owner->recoveryEmail($subject));
        self::assertNull($owner->pendingVerificationEmail($subject));
        self::assertFalse($owner->confirmEmail($subject, hash('sha256', 'owner@example.test'), $now));

        $db->executeStatement('UPDATE symsvel_users SET enabled = 0, email_verified_at = NULL WHERE id = 7');
        self::assertFalse($owner->confirmEmail($subject, hash('sha256', 'owner@example.test'), $now));
        self::assertNull($owner->recoveryEmail($subject));
    }

    public function testChangedEmailCannotBeConfirmedWithOldFingerprint(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY, email VARCHAR(254), enabled BOOLEAN, email_verified_at DATETIME NULL)');
        $db->insert('symsvel_users', [
            'id' => 7, 'email' => 'changed@example.test', 'enabled' => true, 'email_verified_at' => null,
        ]);
        $owner = new UserPasswordResetIdentity($db);
        self::assertFalse($owner->confirmEmail(
            new TokenSubject('app', 7), hash('sha256', 'previous@example.test'), new \DateTimeImmutable(),
        ));
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM symsvel_users WHERE id = 7') ?: null);
    }
}
