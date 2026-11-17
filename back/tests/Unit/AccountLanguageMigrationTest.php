<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class AccountLanguageMigrationTest extends TestCase
{
    public function testLanguageColumnIsNullableAndHasNoForeignKey(): void
    {
        require_once dirname(__DIR__, 3).'/Migration/Version20261117000002.php';
        $schema = new Schema();
        $table = $schema->createTable('symsvel_users');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);
        $migration = (new \ReflectionClass(\DoctrineMigrations\Version20261117000002::class))
            ->newInstanceWithoutConstructor();
        $migration->up($schema);
        self::assertTrue($table->hasColumn('language'));
        self::assertFalse($table->getColumn('language')->getNotnull());
        self::assertSame(4, $table->getColumn('language')->getLength());
        self::assertCount(0, $table->getForeignKeys());
        $migration->down($schema);
        self::assertFalse($table->hasColumn('language'));
        self::assertTrue($table->hasColumn('id'));
    }
}
