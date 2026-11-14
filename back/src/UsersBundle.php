<?php

declare(strict_types=1);

namespace SymSvel\Module\Users;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymSvel\Module\Users\Controller\UsersPingController;
use SymSvel\Module\Users\Auth\UserIdentityProvider;

final class UsersBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->register(UsersPingController::class, UsersPingController::class)
            ->setAutowired(true)
            ->addTag('controller.service_arguments');
        $builder->register(UserIdentityProvider::class, UserIdentityProvider::class)->setAutowired(true);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->extension('doctrine', [
            'orm' => [
                'mappings' => [
                    'SymSvelUsers' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'SymSvel\\Module\\Users\\Entity',
                        'alias' => 'SymSvelUsers',
                    ],
                ],
            ],
        ]);
    }
}
