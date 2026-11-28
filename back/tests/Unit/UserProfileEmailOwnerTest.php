<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Users\Auth\UserProfileEmailOwner;
use SymSvel\Module\Auth\Token\TokenSubject;

final class UserProfileEmailOwnerTest extends TestCase
{
    public function testOnlyItsVerifiedActivePrincipalCanChangeEmail(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement(
            'CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY, email VARCHAR(254) NOT NULL UNIQUE,'
            .' enabled BOOLEAN NOT NULL, email_verified_at TEXT NULL)',
        );
        $db->insert('symsvel_users', [
            'id' => 7, 'email' => 'before@example.test', 'enabled' => true,
            'email_verified_at' => '2026-11-19 10:00:00',
        ]);
        $db->insert('symsvel_users', [
            'id' => 8, 'email' => 'reserved@example.test', 'enabled' => true,
            'email_verified_at' => '2026-11-19 10:00:00',
        ]);
        $owner = new UserProfileEmailOwner($db);
        $now = new \DateTimeImmutable('2026-11-19T14:00:00+00:00');
        $subject = new TokenSubject('app', 7);
        $valid = hash('sha256', 'before@example.test');

        self::assertFalse($owner->confirmNewEmail(
            new TokenSubject('bo', 7), $valid, 'next@example.test', $now,
        ));
        self::assertFalse($owner->confirmNewEmail($subject, str_repeat('f', 64), 'next@example.test', $now));
        self::assertSame('before@example.test', $db->fetchOne('SELECT email FROM symsvel_users WHERE id = 7'));
        self::assertFalse($owner->confirmNewEmail($subject, $valid, 'anonymous-1@EXAMPLE.INVALID', $now));
        self::assertTrue($owner->confirmNewEmail($subject, $valid, 'next@example.test', $now));
        self::assertSame('next@example.test', $db->fetchOne('SELECT email FROM symsvel_users WHERE id = 7'));
        self::assertFalse($owner->confirmNewEmail($subject, $valid, 'another@example.test', $now));
        self::assertSame('reserved@example.test', $db->fetchOne('SELECT email FROM symsvel_users WHERE id = 8'));
    }
}
