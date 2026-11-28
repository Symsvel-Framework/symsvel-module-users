<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Auth\Token\TokenSubject;

/** Internal identity-owner operation; never expose directly as a controller or cron. */
final readonly class UserAnonymization
{
    public function __construct(private Connection $db, private TokenSessionRevoker $sessions)
    {
    }

    /**
     * The caller must validate GDPR policy, confirmed notice and all dependent
     * module obligations inside the callback, using the shared transaction.
     * False refuses the operation. No external effects in that callback.
     */
    public function anonymize(int $id, string $expectedEmail, \DateTimeImmutable $expectedActivity, \DateTimeImmutable $now, callable $authorize): bool
    {
        if ($id < 1 || $this->db->isTransactionActive()) {
            throw new \InvalidArgumentException('Invalid anonymization context.');
        }
        $utc = new \DateTimeZone('UTC');
        $stamp = $now->setTimezone($utc)->format('Y-m-d H:i:s');
        $subject = new TokenSubject('app', $id);

        return $this->sessions->revokeWith($subject, $now, function () use ($id, $expectedEmail, $expectedActivity, $now, $stamp, $utc, $authorize): bool {
            $sql = 'SELECT email, created_at, last_login, anonymized_at FROM symsvel_users WHERE id = ?';
            if ($this->db->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->db->executeStatement('UPDATE symsvel_users SET id = id WHERE id = ?', [$id]);
            } else {
                $sql .= ' FOR UPDATE';
            }
            $row = $this->db->fetchAssociative($sql, [$id]);
            if ($row === false || $row['anonymized_at'] !== null || $row['email'] !== $expectedEmail
                || new \DateTimeImmutable($row['last_login'] ?? $row['created_at'], $utc) != $expectedActivity
                || $expectedActivity >= $now) {
                throw new \DomainException('User no longer eligible for anonymization.');
            }
            // A live session blocks inactivity treatment even if last_login is old.
            foreach (['access', 'refresh'] as $kind) {
                $sql = 'SELECT COUNT(*) FROM symsvel_auth_'.$kind.'_tokens WHERE context = ? AND principal_id = ? AND revoked_at IS NULL AND expires_at > ?';
                if ($kind === 'refresh') {
                    $sql .= ' AND used_at IS NULL';
                }
                if ((int) $this->db->fetchOne($sql, ['app', $id, $now->setTimezone($utc)->format('Y-m-d H:i:s.u')]) > 0) {
                    throw new \DomainException('User has an active session.');
                }
            }
            if ($authorize() !== true) {
                throw new \DomainException('Anonymization not authorized by the retention coordinator.');
            }
            // Random suffix prevents a reserved placeholder collision with legacy accounts.
            $email = 'anonymous-'.$id.'-'.bin2hex(random_bytes(16)).'@example.invalid';
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
            $changed = $this->db->executeStatement(
                'UPDATE symsvel_users SET email = ?, password_hash = ?, enabled = ?, language = NULL, email_verified_at = NULL, last_login = NULL, anonymized_at = ? WHERE id = ? AND anonymized_at IS NULL',
                [$email, $hash, false, $stamp, $id],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::INTEGER],
            );
            if ($changed !== 1) {
                throw new \DomainException('User changed concurrently.');
            }
            foreach (['symsvel_auth_email_verification_tokens', 'symsvel_auth_password_reset_tokens', 'symsvel_auth_profile_email_changes', 'symsvel_auth_access_tokens', 'symsvel_auth_refresh_tokens'] as $table) {
                $this->db->delete($table, ['context' => 'app', 'principal_id' => $id]);
            }
            $this->db->delete('symsvel_auth_app_invitations', ['principal_id' => $id]);

            return true;
        });
    }
}
