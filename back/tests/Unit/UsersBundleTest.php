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

    public function testRegistersNativeRetentionOwnerWhenContractInstalled(): void
    {
        $builder = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        $configurator = (new \ReflectionClass(\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator::class))->newInstanceWithoutConstructor();
        (new UsersBundle())->loadExtension([], $configurator, $builder);
        self::assertTrue($builder->hasDefinition(\SymSvel\Module\Users\Service\UserAnonymization::class));
        self::assertTrue($builder->getDefinition(\SymSvel\Module\Users\Gdpr\UserRetentionOwner::class)->hasTag('symsvel.gdpr.account_retention_owner'));
    }
}
