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
use SymSvel\Module\Auth\Schema\ProfileEmailChangeSchema;
use SymSvel\Module\Users\Service\UserAnonymization;
use SymSvel\Module\Users\Service\InactiveUsers;
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
        $db->executeStatement('CREATE TABLE symsvel_users (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(254) NOT NULL UNIQUE, language VARCHAR(4) NULL, password_hash VARCHAR(255) NOT NULL, enabled BOOLEAN NOT NULL, email_verified_at DATETIME NULL, created_at DATETIME NOT NULL, anonymized_at DATETIME NULL, last_login DATETIME NULL)');
        $schema = new Schema();
        AuthTokenSchema::apply($schema);
        EmailVerificationSchema::apply($schema);
        PasswordResetSchema::apply($schema);
        AppInvitationSchema::apply($schema);
        ProfileEmailChangeSchema::apply($schema);
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
        self::assertNull($db->fetchOne('SELECT language FROM symsvel_users WHERE id = ?', [$id]));
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

    public function testPendingInvitedUserWithChangedAddressNeedsNewActivationLink(): void
    {
        [$users, $db] = $this->fixture();
        $id = $users->invite('initial@example.test', new \DateTimeImmutable('2026-11-16T14:00:00Z'));
        self::assertSame('initial@example.test', $users->pendingInvitationEmail($id));
        self::assertSame('new@example.test', $users->update($id, ' NEW@Example.test ', null));
        self::assertSame('new@example.test', $users->pendingInvitationEmail($id));
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM symsvel_users WHERE id = ?', [$id]));
    }

    public function testNeutralizationRetainsIdAndPermanentlyBlocksOrdinaryAdministration(): void
    {
        [$users, $db] = $this->fixture();
        $activity = new \DateTimeImmutable('2025-01-01T00:00:00Z');
        $now = new \DateTimeImmutable('2026-11-28T10:00:00Z');
        $id = $users->create('person@example.test', 'correct-password-123', $activity, 'fr');
        $neutralizer = new UserAnonymization($db, new TokenSessionRevoker(new DbalTokenStore($db)));
        try {
            $neutralizer->anonymize($id, 'person@example.test', $activity, $now, static fn () => false);
            self::fail('Unauthorized neutralization accepted.');
        } catch (\DomainException) {
        }
        self::assertSame('person@example.test', $db->fetchOne('SELECT email FROM symsvel_users WHERE id = ?', [$id]));
        self::assertTrue($neutralizer->anonymize($id, 'person@example.test', $activity, $now, static fn () => true));
        $row = $db->fetchAssociative('SELECT * FROM symsvel_users WHERE id = ?', [$id]);
        self::assertMatchesRegularExpression('/^anonymous-'.$id.'-[a-f0-9]{32}@example\.invalid$/D', $row['email']);
        self::assertSame(0, (int) $row['enabled']);
        self::assertNull($row['language']);
        self::assertNull($row['last_login']);
        self::assertNotNull($row['anonymized_at']);
        self::assertFalse(password_verify('correct-password-123', $row['password_hash']));
        foreach ([static fn () => $users->update($id, 'reactivated@example.test', true), static fn () => $users->delete($id)] as $operation) {
            try {
                $operation();
                self::fail('Neutralized identity could be modified.');
            } catch (\DomainException) {
            }
        }
        self::assertNull($users->pendingInvitationEmail($id));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_users'));
    }

    public function testPhysicalDeletionRequiresCurrentActivityNoLiveSessionAndExplicitApproval(): void
    {
        [$users, $db] = $this->fixture();
        $old = new \DateTimeImmutable('2025-01-01T00:00:00Z');
        $now = new \DateTimeImmutable('2026-11-28T10:00:00Z');
        $id = $users->invite('delete@example.test', $old);
        $owner = new UserAnonymization($db, new TokenSessionRevoker(new DbalTokenStore($db)));
        $calls = 0;
        $approval = static function () use (&$calls): bool { ++$calls; return true; };
        $db->insert('symsvel_auth_refresh_tokens', [
            'context' => 'app', 'principal_id' => $id, 'family_id' => str_repeat('a', 32),
            'token_hash' => str_repeat('c', 64), 'created_at' => '2026-11-27 10:00:00',
            'expires_at' => '2026-12-01 10:00:00',
        ]);
        foreach ([
            static fn () => $owner->deleteInactive($id, 'delete@example.test', $old->modify('-1 day'), $now, $approval),
            static fn () => $owner->deleteInactive($id, 'delete@example.test', $old, $now, $approval),
        ] as $operation) {
            try {
                $operation();
                self::fail('Unsafe deletion accepted.');
            } catch (\DomainException) {
            }
        }
        self::assertSame(0, $calls);
        $db->update('symsvel_auth_refresh_tokens', ['revoked_at' => '2026-11-27 11:00:00'], ['principal_id' => $id, 'context' => 'app']);
        try {
            $owner->deleteInactive($id, 'delete@example.test', $old, $now, static fn () => false);
            self::fail('Deletion without approval accepted.');
        } catch (\DomainException) {
        }
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_users'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_refresh_tokens'));
        self::assertTrue($owner->deleteInactive($id, 'delete@example.test', $old, $now, $approval));
        self::assertSame(1, $calls);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_users'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_auth_refresh_tokens'));
    }

    public function testPhysicalDeletionRollsBackModuleWorkWhenAReferencePreventsDeletion(): void
    {
        [$users, $db] = $this->fixture();
        $db->executeStatement('PRAGMA foreign_keys = ON');
        $db->executeStatement('CREATE TABLE retained_document (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES symsvel_users(id) ON DELETE RESTRICT)');
        $db->executeStatement('CREATE TABLE removable_profile (user_id INTEGER PRIMARY KEY)');
        $old = new \DateTimeImmutable('2025-01-01T00:00:00Z');
        $now = new \DateTimeImmutable('2026-11-28T10:00:00Z');
        $id = $users->invite('retained@example.test', $old);
        $db->insert('retained_document', ['id' => 1, 'user_id' => $id]);
        $db->insert('removable_profile', ['user_id' => $id]);
        $owner = new UserAnonymization($db, new TokenSessionRevoker(new DbalTokenStore($db)));
        try {
            $owner->deleteInactive($id, 'retained@example.test', $old, $now, static function () use ($db, $id): bool {
                $db->delete('removable_profile', ['user_id' => $id]);
                return true;
            });
            self::fail('Referenced identity deleted.');
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException) {
        }
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM removable_profile'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM retained_document'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_users'));
    }

    public function testInactivityScanUsesLastLoginOrCreationAndExcludesLiveSessionsAndNeutralizedAccounts(): void
    {
        [$users, $db] = $this->fixture();
        $old = new \DateTimeImmutable('2025-01-01T00:00:00Z');
        $now = new \DateTimeImmutable('2026-11-28T10:00:00Z');
        $never = $users->invite('never@example.test', $old);
        $recent = $users->invite('recent@example.test', $old);
        $inactive = $users->invite('inactive@example.test', $old);
        $active = $users->invite('active@example.test', $old);
        $neutral = $users->invite('neutral@example.test', $old);
        $db->update('symsvel_users', ['last_login' => '2026-11-27 10:00:00'], ['id' => $recent]);
        $db->update('symsvel_users', ['last_login' => '2025-06-01 10:00:00', 'email_verified_at' => '2025-01-01 10:00:00'], ['id' => $inactive]);
        $db->update('symsvel_users', ['anonymized_at' => '2026-11-01 10:00:00'], ['id' => $neutral]);
        $db->insert('symsvel_auth_refresh_tokens', [
            'context' => 'app', 'principal_id' => $active, 'family_id' => str_repeat('a', 32),
            'token_hash' => str_repeat('b', 64), 'created_at' => '2026-11-27 10:00:00',
            'expires_at' => '2026-12-01 10:00:00',
        ]);
        $scan = new InactiveUsers($db);
        $maximum = $scan->maximumId();
        $late = $users->invite('late@example.test', $old);
        self::assertGreaterThan($maximum, $late);
        $before = $now->modify('-11 months');
        $first = $scan->batch(0, $maximum, $before, $now, 1);
        self::assertSame([$never], array_column($first, 'id'));
        self::assertEquals($old, $first[0]['activity']);
        self::assertFalse($first[0]['verified']);
        $second = $scan->batch($never, $maximum, $before, $now, 100);
        self::assertSame([$inactive], array_column($second, 'id'));
        self::assertEquals(new \DateTimeImmutable('2025-06-01T10:00:00Z'), $second[0]['activity']);
        self::assertTrue($second[0]['verified']);
        self::assertSame([], $scan->batch($inactive, $maximum, $before, $now));
    }

    public function testRetainedMailboxNamespaceCannotBeUsedForOrdinaryAccounts(): void
    {
        [$users, $db] = $this->fixture();
        $now = new \DateTimeImmutable();
        $id = $users->invite('ordinary@example.test', $now);
        foreach ([
            static fn () => $users->invite('anonymous-1@example.invalid', $now),
            static fn () => $users->update($id, 'ANONYMOUS-2@EXAMPLE.INVALID', true),
        ] as $operation) {
            try {
                $operation();
                self::fail('Reserved address accepted.');
            } catch (\InvalidArgumentException) {
            }
        }
        self::assertSame('ordinary@example.test', $db->fetchOne('SELECT email FROM symsvel_users WHERE id = ?', [$id]));
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
