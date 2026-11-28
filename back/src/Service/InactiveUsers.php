<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** Read-only, bounded candidate scan. GDPR owns all policy decisions. */
final readonly class InactiveUsers
{
    public function __construct(private Connection $db)
    {
    }

    public function maximumId(): int
    {
        return (int) $this->db->fetchOne('SELECT COALESCE(MAX(id), 0) FROM symsvel_users');
    }

    /**
     * The caller supplies a conservative activity cutoff; this is not an erasure
     * decision. The owner must lock and recheck the current row before mutation.
     *
     * @return list<array{id:int,email:string,language:?string,activity:\DateTimeImmutable,verified:bool}>
     */
    public function batch(int $afterId, int $maximumId, \DateTimeImmutable $before, \DateTimeImmutable $now, int $limit = 100): array
    {
        if ($afterId < 0 || $maximumId < 0 || $limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Invalid inactivity scan bounds.');
        }
        if ($afterId >= $maximumId) {
            return [];
        }
        $utc = new \DateTimeZone('UTC');
        $query = $this->db->createQueryBuilder()
            ->select('u.id', 'u.email', 'u.language', 'u.email_verified_at', 'u.created_at', 'u.last_login')
            ->from('symsvel_users', 'u')
            ->where('u.id > :afterId AND u.id <= :maximumId AND u.anonymized_at IS NULL')
            ->andWhere('COALESCE(u.last_login, u.created_at) < :before')
            ->andWhere("NOT EXISTS (SELECT 1 FROM symsvel_auth_access_tokens a WHERE a.context = 'app' AND a.principal_id = u.id AND a.revoked_at IS NULL AND a.expires_at > :now)")
            ->andWhere("NOT EXISTS (SELECT 1 FROM symsvel_auth_refresh_tokens r WHERE r.context = 'app' AND r.principal_id = u.id AND r.revoked_at IS NULL AND r.used_at IS NULL AND r.expires_at > :now)")
            ->setParameter('afterId', $afterId, ParameterType::INTEGER)
            ->setParameter('maximumId', $maximumId, ParameterType::INTEGER)
            ->setParameter('before', $before->setTimezone($utc)->format('Y-m-d H:i:s.u'))
            ->setParameter('now', $now->setTimezone($utc)->format('Y-m-d H:i:s.u'))
            ->orderBy('u.id', 'ASC')
            ->setMaxResults($limit);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'language' => $row['language'],
            'activity' => new \DateTimeImmutable($row['last_login'] ?? $row['created_at'], $utc),
            'verified' => $row['email_verified_at'] !== null,
        ], $query->executeQuery()->fetchAllAssociative());
    }
}
