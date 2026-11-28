<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use SymSvel\Module\Auth\Token\TokenSessionRevoker;
use SymSvel\Module\Auth\Token\AppPrincipalSecurityData;
use SymSvel\Module\Auth\Token\TokenSubject;

/** Internal identity-owner operation; never expose directly as a controller or cron. */
final readonly class UserAnonymization
{
    public function __construct(private Connection $db, private TokenSessionRevoker $sessions, private AppPrincipalSecurityData $security)
    {
    }

    /**
     * The caller must validate GDPR policy, confirmed notice and all dependent
     * module obligations inside the callback, using the shared transaction.
     * False refuses the operation. No external effects in that callback.
     */
    public function anonymize(int $id, string $expectedEmail, \DateTimeImmutable $expectedActivity, \DateTimeImmutable $now, callable $authorize): bool
    {
        return $this->process($id, $expectedEmail, $expectedActivity, $now, $authorize, false);
    }

    /** Physical deletion is a distinct, explicitly authorized owner operation. */
    public function deleteInactive(int $id, string $expectedEmail, \DateTimeImmutable $expectedActivity, \DateTimeImmutable $now, callable $authorize): bool
    {
        return $this->process($id, $expectedEmail, $expectedActivity, $now, $authorize, true);
    }

    private function process(int $id, string $expectedEmail, \DateTimeImmutable $expectedActivity, \DateTimeImmutable $now, callable $authorize, bool $delete): bool
    {
        if ($id < 1 || $this->db->isTransactionActive()) {
            throw new \InvalidArgumentException('Invalid anonymization context.');
        }
        $utc = new \DateTimeZone('UTC');
        $stamp = $now->setTimezone($utc)->format('Y-m-d H:i:s');
        $subject = new TokenSubject('app', $id);

        return $this->sessions->revokeWith($subject, $now, function () use ($id, $expectedEmail, $expectedActivity, $now, $stamp, $utc, $authorize, $delete): bool {
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
            if ($this->security->hasActiveSession($id, $now)) {
                throw new \DomainException('User has an active session.');
            }
            if ($authorize() !== true) {
                throw new \DomainException('Account treatment not authorized by the retention coordinator.');
            }
            if (!$delete) {
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
            }
            $this->security->erase($id);
            if ($delete && $this->db->executeStatement('DELETE FROM symsvel_users WHERE id = ? AND anonymized_at IS NULL', [$id]) !== 1) {
                throw new \DomainException('User changed concurrently.');
            }

            return true;
        });
    }
}
