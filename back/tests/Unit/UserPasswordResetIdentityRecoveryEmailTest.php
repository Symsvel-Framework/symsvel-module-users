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

        $provider = new UserPasswordResetIdentity(DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]));

        self::assertSame('app', $provider->authContext());
        self::assertNull($provider->recoveryEmail(new TokenSubject('app', 1)));
        self::assertNull($provider->recoveryEmail(new TokenSubject('bo', 1)));
    }
}
