<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Users\Auth\UserPasswordResetIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

final class UserPasswordResetIdentityRecoveryEmailTest extends TestCase
{
    public function testRecoveryEmailFailsClosedUntilMailboxVerificationExists(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $db = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $db->executeStatement(
            'CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY, email VARCHAR(254) NOT NULL, enabled BOOLEAN NOT NULL, email_verified_at DATETIME NULL)',
        );
        $provider = new UserPasswordResetIdentity($db);

        self::assertSame('app', $provider->authContext());
        self::assertNull($provider->recoveryEmail(new TokenSubject('app', 1)));
        self::assertNull($provider->recoveryEmail(new TokenSubject('bo', 1)));
    }
}
