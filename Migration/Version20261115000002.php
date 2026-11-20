<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds an unverified-by-default mailbox state without rewriting the initial schema. */
final class Version20261115000002 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $schema->getTable('symsvel_users')
            ->addColumn('email_verified_at', 'datetime_immutable', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('symsvel_users')->dropColumn('email_verified_at');
    }
}
