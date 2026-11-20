<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class EmailVerificationMigrationTest extends TestCase
{
    public function testAdditiveSchemaMigrationDoesNotRewriteExistingColumns(): void
    {
        require_once dirname(__DIR__, 3).'/Migration/Version20261115000002.php';
        $schema = new Schema();
        $table = $schema->createTable('symsvel_users');
        $table->addColumn('id', 'integer');
        $table->addColumn('email', 'string', ['length' => 254]);
        $table->setPrimaryKey(['id']);

        $migration = (new \ReflectionClass(\DoctrineMigrations\Version20261115000002::class))
            ->newInstanceWithoutConstructor();
        $migration->up($schema);
        self::assertTrue($table->hasColumn('email_verified_at'));
        self::assertFalse($table->getColumn('email_verified_at')->getNotnull());
        self::assertTrue($table->hasColumn('email'));
        self::assertNotNull($table->getPrimaryKey());

        $migration->down($schema);
        self::assertFalse($table->hasColumn('email_verified_at'));
        self::assertTrue($table->hasColumn('email'));
    }
}
