<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\Users\UsersBundle;

final class UsersBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        self::assertInstanceOf(UsersBundle::class, new UsersBundle());
    }
}
