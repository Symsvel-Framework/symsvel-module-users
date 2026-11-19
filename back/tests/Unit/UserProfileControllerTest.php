<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Users\Controller\UserProfileController;
use SymSvel\Module\Users\Entity\User;

final class UserProfileControllerTest extends TestCase
{
    public function testProfileDisclosesOnlyPublicAccountFields(): void
    {
        $user = new User('person@example.test', password_hash('private-passphrase', PASSWORD_ARGON2ID));
        $user->setLanguage('fr');
        $profile = UserProfileController::present($user);
        self::assertSame(['id', 'email', 'language', 'email_verified'], array_keys($profile));
        self::assertSame('person@example.test', $profile['email']);
        self::assertSame('fr', $profile['language']);
        self::assertArrayNotHasKey('password_hash', $profile);
    }

    public function testProfileRoutesBelongOnlyToAuthenticatedAppArea(): void
    {
        foreach (['show' => 'GET', 'update' => 'PATCH'] as $method => $verb) {
            $attributes = (new \ReflectionMethod(UserProfileController::class, $method))
                ->getAttributes(Route::class);
            self::assertCount(1, $attributes);
            // Attribute metadata is not a Symfony\Component\\Routing\\Route object.
            $arguments = $attributes[0]->getArguments();
            self::assertSame('/api/users/profile', $arguments[0]);
            self::assertSame([$verb], $arguments['methods']);
        }
    }
}
