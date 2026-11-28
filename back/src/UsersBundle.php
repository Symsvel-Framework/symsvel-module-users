<?php

declare(strict_types=1);

namespace SymSvel\Module\Users;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymSvel\Module\Users\Permission\UsersPermissionProvider;
use SymSvel\Module\Users\Controller\UsersPingController;
use Symfony\Component\DependencyInjection\Reference;
use SymSvel\Module\Users\Controller\UserRegistrationController;
use SymSvel\Module\Users\Controller\BoUsersController;
use SymSvel\Module\Users\Controller\UserProfileController;
use SymSvel\Module\Users\Service\UserAdministration;
use SymSvel\Module\Users\Service\InactiveUsers;
use SymSvel\Module\Users\Service\UserRegistration;
use SymSvel\Module\Users\Auth\UserIdentityProvider;
use SymSvel\Module\Users\Auth\UserLastLoginListener;
use SymSvel\Module\Users\Auth\UserProfileEmailOwner;
use SymSvel\Module\Users\Auth\UserPasswordResetIdentity;
use SymSvel\Module\Users\Auth\UserInvitationIdentity;

final class UsersBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->register(UsersPingController::class, UsersPingController::class)
            ->setAutowired(true)
            ->addTag('controller.service_arguments');
        $builder->register(UsersPermissionProvider::class, UsersPermissionProvider::class)
            ->addTag('symsvel.acls.permission_provider');
        $builder->register(UserIdentityProvider::class, UserIdentityProvider::class)
            ->setAutowired(true)->addTag('symsvel.auth.identity_provider');
        $builder->register(UserLastLoginListener::class)
            ->setArgument(0, new Reference('doctrine.dbal.default_connection'))
            ->addTag('symsvel.auth.session_issued_listener');
        $builder->register(UserRegistration::class, UserRegistration::class)
            ->setAutowired(true)
            ->setArgument('$connection', new Reference('doctrine.dbal.default_connection'));
        $builder->register(UserAdministration::class, UserAdministration::class)->setAutowired(true);
        $builder->register(InactiveUsers::class)->setAutowired(true);
        $builder->register(UserProfileController::class, UserProfileController::class)
            ->setAutowired(true)->addTag('controller.service_arguments');
        $builder->register(BoUsersController::class, BoUsersController::class)
            ->setAutowired(true)->addTag('controller.service_arguments');
        $builder->register(UserRegistrationController::class, UserRegistrationController::class)
            ->setAutowired(true)
            ->addTag('controller.service_arguments');
        $builder->register(UserInvitationIdentity::class, UserInvitationIdentity::class)
            ->setAutowired(true)->addTag('symsvel.auth.app_invitation_identity');
        $builder->register(UserProfileEmailOwner::class, UserProfileEmailOwner::class)
            ->setAutowired(true)->addTag('symsvel.auth.profile_email_owner');
        $builder->register(UserPasswordResetIdentity::class, UserPasswordResetIdentity::class)
            ->setAutowired(true)->addTag('symsvel.auth.password_reset_identity')
            ->addTag('symsvel.auth.email_verification_identity');
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
