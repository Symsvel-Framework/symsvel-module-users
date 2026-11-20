<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Permission;

use SymSvel\Module\Acls\Permission\PermissionDefinition;
use SymSvel\Module\Acls\Permission\PermissionProviderInterface;

/** Exact BO actions implemented by the module's authorized endpoints. */
final class UsersPermissionProvider implements PermissionProviderInterface
{
    public function permissions(): iterable
    {
        yield new PermissionDefinition('users', 'list', 'Lister les utilisateurs');
        yield new PermissionDefinition('users', 'create', 'Inviter un utilisateur');
        yield new PermissionDefinition('users', 'update', 'Modifier un utilisateur');
        yield new PermissionDefinition('users', 'delete', 'Supprimer un utilisateur');
    }
}
