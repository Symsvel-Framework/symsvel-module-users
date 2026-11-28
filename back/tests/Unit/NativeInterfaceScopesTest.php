<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NativeInterfaceScopesTest extends TestCase
{
    public function testOnlyFunctionalInterfaceScopesAreDeclared(): void
    {
        $root = dirname(__DIR__, 3);
        $manifest = json_decode((string) file_get_contents($root.'/module.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['back' => 'back', 'bo' => 'bo', 'app' => 'app'], $manifest['components']);
        self::assertFileExists($root.'/bo/src/routes/users/+page.svelte');
        self::assertFileDoesNotExist($root.'/app/src/routes/users/+page.svelte');
        self::assertFileDoesNotExist($root.'/front/src/routes/users/+page.svelte');
        self::assertFileExists($root.'/app/src/routes/profile/+page.svelte');
        self::assertFileExists($root.'/app/src/routes/register/+page.svelte');
    }
}
