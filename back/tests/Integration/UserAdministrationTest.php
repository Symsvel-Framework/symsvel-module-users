<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Auth\Schema\AuthTokenSchema;
use SymSvel\Module\Auth\Schema\AppInvitationSchema;
use SymSvel\Module\Auth\Schema\EmailVerificationSchema;
use SymSvel\Module\Auth\Schema\PasswordResetSchema;
use SymSvel\Module\Auth\Token\DbalTokenStore;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Users\Service\UserAdministration;

final class UserAdministrationTest extends TestCase
{
    /** @return array{UserAdministration, Connection} */
    private function fixture(): array
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, email_verified_at DATETIME NULL, created_at DATETIME NOT NULL)');
        $schema = new Schema();
        AuthTokenSchema::apply($schema);
        EmailVerificationSchema::apply($schema);
        PasswordResetSchema::apply($schema);
        AppInvitationSchema::apply($schema);
        foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) {
            $db->executeStatement($sql);
        }
        return [new UserAdministration($db, new TokenSessionRevoker(new DbalTokenStore($db))), $db];
    }

    public function testCreateRequiresPendingMailboxAndArgon2id(): void
    {
        [$users, $db] = $this->fixture();
        $id = $users->create(' TEST@Example.test ', 'correct-password-123', new \DateTimeImmutable('2026-11-16T14:00:00Z'));
        self::assertGreaterThan(0, $id);
        $row = $db->fetchAssociative('SELECT email, enabled, password_hash, email_verified_at FROM symsvel_users WHERE id = ?', [$id]);
        self::assertSame('test@example.test', $row['email']);
        self::assertSame(1, (int) $row['enabled']);
        self::assertNull($row['email_verified_at']);
        self::assertTrue(password_verify('correct-password-123', $row['password_hash']));
        self::assertSame('argon2id', password_get_info($row['password_hash'])['algoName']);

        $this->expectException(\DomainException::class);
        $users->create('test@example.test', 'second-password-123', new \DateTimeImmutable());
    }

    public function testInviteCreatesAnUnverifiedAccountWithUnknownRandomCredential(): void
    {
        [$users, $db] = $this->fixture();
        $id = $users->invite(' INVITED@Example.test ', new \DateTimeImmutable('2026-11-16T14:00:00Z'));
        $row = $db->fetchAssociative('SELECT email, enabled, password_hash, email_verified_at FROM symsvel_users WHERE id = ?', [$id]);
        self::assertSame('invited@example.test', $row['email']);
        self::assertNull($row['email_verified_at']);
        self::assertSame(1, (int) $row['enabled']);
        self::assertSame('argon2id', password_get_info((string) $row['password_hash'])['algoName']);
        self::assertSame('invited@example.test', $users->pendingInvitationEmail($id));
        self::assertNull($users->pendingInvitationEmail(999));
    }

    public function testChangingEmailClearsVerificationAndPendingTokens(): void
    {
        [$users, $db] = $this->fixture();
        $id = $users->create('before@example.test', 'correct-password-123', new \DateTimeImmutable());
        $db->update('symsvel_users', ['email_verified_at' => '2026-11-16 12:00:00'], ['id' => $id]);
        foreach (['symsvel_auth_email_verification_tokens', 'symsvel_auth_password_reset_tokens'] as $table) {
            $values = [
                'context' => 'app',
                'principal_id' => $id,
                'token_hash' => hash('sha256', $table),
                'created_at' => '2026-11-16 13:00:00',
                'expires_at' => '2026-11-16 15:00:00',
            ];
            if ($table === 'symsvel_auth_email_verification_tokens') {
                $values['email_hash'] = hash('sha256', 'before@example.test');
            }
            $db->insert($table, $values);
        }
        $db->insert('symsvel_auth_app_invitations', [
            'principal_id' => $id,
            'email_hash' => hash('sha256', 'before@example.test'),
            'token_hash' => hash('sha256', 'invited'),
            'created_at' => '2026-11-16 13:00:00',
            'expires_at' => '2026-11-16 15:00:00',
        ]);
        self::assertSame('after@example.test', $users->update($id, ' AFTER@Example.test ', false));
        $row = $db->fetchAssociative('SELECT email, enabled, email_verified_at FROM symsvel_users WHERE id = ?', [$id]);
        self::assertSame('after@example.test', $row['email']);
        self::assertSame(0, (int) $row['enabled']);
        self::assertNull($row['email_verified_at']);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_email_verification_tokens'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_password_reset_tokens'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_app_invitations'));

        $users->delete($id);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_users'));
    }

    public function testInvalidPasswordAndUnknownUserAreRejected(): void
    {
        [$users] = $this->fixture();
        try {
            $users->create('valid@example.test', 'short', new \DateTimeImmutable());
            self::fail('Short password accepted');
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(\DomainException::class);
        $users->update(999, null, true);
    }
}
