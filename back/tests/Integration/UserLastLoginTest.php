<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Auth\Schema\AuthTokenSchema;
use SymSvel\Module\Auth\Token\DbalTokenStore;
use SymSvel\Module\Auth\Token\SessionIssuedListener;
use SymSvel\Module\Auth\Token\TokenFactory;
use SymSvel\Module\Auth\Token\TokenIssuer;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Users\Auth\UserLastLoginListener;

final class UserLastLoginTest extends TestCase
{
    private Connection $db;
    private TokenIssuer $issuer;

    protected function setUp(): void
    {
        self::assertTrue(extension_loaded('pdo_sqlite'), 'pdo_sqlite is required.');
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        $table = $schema->createTable('symsvel_users');
        $table->addColumn('id', 'integer');
        $table->addColumn('enabled', 'boolean');
        $table->addColumn('email_verified_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        require_once dirname(__DIR__, 3).'/Migration/Version20261127134501.php';
        $migration = (new \ReflectionClass(\DoctrineMigrations\Version20261127134501::class))->newInstanceWithoutConstructor();
        $migration->up($schema);
        self::assertFalse($table->getColumn('last_login')->getNotnull());
        AuthTokenSchema::apply($schema);
        foreach ($schema->toSql($this->db->getDatabasePlatform()) as $sql) {
            $this->db->executeStatement($sql);
        }
        $this->db->insert('symsvel_users', ['id' => 7, 'enabled' => 1, 'email_verified_at' => '2026-11-01 09:00:00']);
        $this->db->insert('symsvel_users', ['id' => 8, 'enabled' => 0, 'email_verified_at' => '2026-11-01 09:00:00']);
        $this->db->insert('symsvel_users', ['id' => 9, 'enabled' => 1, 'email_verified_at' => null]);
        $this->issuer = new TokenIssuer(new DbalTokenStore($this->db), new TokenFactory(), [new UserLastLoginListener($this->db)]);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->close();
        }
    }

    public function testInitialSessionRecordsMonotonicUtcTimestampOnlyForApp(): void
    {
        $at = new \DateTimeImmutable('2026-11-27T14:00:00+01:00');
        self::assertNull($this->db->fetchOne('SELECT last_login FROM symsvel_users WHERE id = 7'));
        $this->issuer->issue(new TokenSubject('bo', 7), $at);
        self::assertNull($this->db->fetchOne('SELECT last_login FROM symsvel_users WHERE id = 7'));
        self::assertNull($this->issuer->issueWhen(new TokenSubject('app', 7), $at, static fn (): bool => false));
        self::assertNull($this->db->fetchOne('SELECT last_login FROM symsvel_users WHERE id = 7'));
        $this->issuer->issue(new TokenSubject('app', 7), $at);
        self::assertSame('2026-11-27 13:00:00', $this->db->fetchOne('SELECT last_login FROM symsvel_users WHERE id = 7'));
        $this->issuer->issueWhen(new TokenSubject('app', 7), $at->modify('+1 minute'), static fn (): bool => true);
        $this->issuer->issue(new TokenSubject('app', 7), $at);
        self::assertSame('2026-11-27 13:01:00', $this->db->fetchOne('SELECT last_login FROM symsvel_users WHERE id = 7'));
    }

    public function testInvalidAccountRollsBackTokenIssuance(): void
    {
        foreach ([8, 9, 99] as $id) {
            try {
                $this->issuer->issue(new TokenSubject('app', $id), new \DateTimeImmutable());
                self::fail('An ineligible user must not receive a session.');
            } catch (\DomainException) {
                self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_access_tokens'));
                self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_refresh_tokens'));
            }
        }
    }

    public function testLaterListenerFailureRollsBackTokensAndLastLogin(): void
    {
        $failure = new class implements SessionIssuedListener {
            public function onSessionIssued(TokenSubject $subject, \DateTimeImmutable $at): void
            {
                throw new \RuntimeException('Forced transactional failure.');
            }
        };
        $issuer = new TokenIssuer(new DbalTokenStore($this->db), new TokenFactory(),
            [new UserLastLoginListener($this->db), $failure]);
        try {
            $issuer->issue(new TokenSubject('app', 7), new \DateTimeImmutable());
            self::fail('Listener failure must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Forced transactional failure.', $exception->getMessage());
        }
        self::assertNull($this->db->fetchOne('SELECT last_login FROM symsvel_users WHERE id = 7'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_access_tokens'));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_refresh_tokens'));
    }
}
