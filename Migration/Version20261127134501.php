<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Additive timestamp; historical accounts keep NULL. */
final class Version20261127134501 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('symsvel_users')->addColumn('last_login', 'datetime_immutable', [
            'notnull' => false,
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('symsvel_users')->dropColumn('last_login');
    }
}
