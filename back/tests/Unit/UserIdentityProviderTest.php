<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Auth\Identity\PasswordPrincipal;
use SymSvel\Module\Auth\Identity\PrincipalStatus;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Users\Auth\UserIdentityProvider;

final class UserIdentityProviderTest extends TestCase
{
    public function testImplementsAuthContracts(): void
    {
        self::assertTrue(is_subclass_of(UserIdentityProvider::class, PasswordPrincipal::class));
        self::assertTrue(is_subclass_of(UserIdentityProvider::class, PrincipalStatus::class));
    }

    public function testRejectsBoContextAndInvalidEmailWithoutDatabaseAccess(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('getRepository');
        $manager->expects(self::never())->method('find');
        $provider = new UserIdentityProvider($manager);

        self::assertNull($provider->authenticate('bo', 'user@example.test', 'secret'));
        self::assertNull($provider->authenticate('app', 'invalid', 'secret'));
        self::assertNull($provider->authenticate('app', str_repeat('x', 250).'@example.test', 'secret'));
        self::assertFalse($provider->isActive(new TokenSubject('bo', 1)));
    }
}
