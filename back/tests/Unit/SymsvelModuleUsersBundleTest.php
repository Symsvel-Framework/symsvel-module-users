<?php

declare(strict_types=1);

namespace SymSvel\Module\SymsvelModuleUsers\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymSvel\Module\SymsvelModuleUsers\SymsvelModuleUsersBundle;

final class SymsvelModuleUsersBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        self::assertInstanceOf(SymsvelModuleUsersBundle::class, new SymsvelModuleUsersBundle());
    }
}
