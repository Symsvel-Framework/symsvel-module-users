<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Account language preference is a plain ISO code, not a reference to Languages. */
final class Version20261117000002 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('symsvel_users')->addColumn('language', 'string', [
            'length' => 4,
            'notnull' => false,
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('symsvel_users')->dropColumn('language');
    }
}
