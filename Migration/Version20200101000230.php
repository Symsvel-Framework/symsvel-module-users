<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Immutable, platform-independent Users schema migration. */
final class Version20200101000230 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('symsvel_users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('email', 'string', ['length' => 254]);
        $table->addColumn('password_hash', 'string', ['length' => 255]);
        $table->addColumn('enabled', 'boolean', ['default' => true]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'uniq_symsvel_users_email');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('symsvel_users');
    }
}
