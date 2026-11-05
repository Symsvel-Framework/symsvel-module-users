<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Users\Entity\User;

final class UserTest extends TestCase
{
    public function testEntityStartsWithoutDatabaseIdentifier(): void
    {
        $entity = new User('user@example.test', password_hash('a-secure-password', PASSWORD_ARGON2ID));
        self::assertNull($entity->getId());
        self::assertSame('user@example.test', $entity->getEmail());
    }
}
