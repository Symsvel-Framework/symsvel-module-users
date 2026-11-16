<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Auth\Identity\PasswordResetLimiter;
use SymSvel\Module\Auth\Schema\PasswordResetLimitSchema;
use SymSvel\Module\Users\Service\UserRegistration;

final class UserRegistrationTest extends TestCase
{
    public function testPublicRegistrationIsUnverifiedAndDoesNotDiscloseDuplicates(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(254) UNIQUE, password_hash VARCHAR(255), enabled BOOLEAN, created_at DATETIME, email_verified_at DATETIME NULL)');
        $schema = new Schema();
        PasswordResetLimitSchema::apply($schema);
        foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) {
            $db->executeStatement($sql);
        }
        $register = new UserRegistration($db, new PasswordResetLimiter($db, 'a-strong-secret-for-registration'));
        $now = new \DateTimeImmutable('2026-11-16T10:00:00+00:00');

        self::assertTrue($register->register(' New@Example.test ', 'long-password-test', '192.0.2.10', $now));
        $row = $db->fetchAssociative('SELECT email, password_hash, enabled, email_verified_at FROM symsvel_users');
        self::assertSame('new@example.test', $row['email']);
        self::assertTrue(password_verify('long-password-test', $row['password_hash']));
        self::assertNull($row['email_verified_at']);
        self::assertEquals(1, $row['enabled']);

        self::assertFalse($register->register('new@example.test', 'different-password', '192.0.2.11', $now));
        self::assertFalse($register->register('new@example.test', 'different-password', '192.0.2.11', $now->modify('+5 minutes')));
        self::assertSame($row['password_hash'], $db->fetchOne('SELECT password_hash FROM symsvel_users'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_users'));
    }

    public function testInvalidInputIsRejectedWithoutPersistence(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $registration = new UserRegistration($db, new PasswordResetLimiter($db, 'a-strong-secret-for-registration'));
        $this->expectException(\InvalidArgumentException::class);
        $registration->register('invalid', 'short', '192.0.2.10', new \DateTimeImmutable());
    }
}
