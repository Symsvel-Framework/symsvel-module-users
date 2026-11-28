<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128101501 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('symsvel_users')->addColumn('anonymized_at', 'datetime_immutable', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('symsvel_users')->dropColumn('anonymized_at');
    }
}
