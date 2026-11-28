<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Gdpr;

use SymSvel\Module\Gdpr\Retention\AccountRetentionOwner;
use SymSvel\Module\Gdpr\Retention\InactivityCandidate;
use SymSvel\Module\Users\Service\InactiveUsers;
use SymSvel\Module\Users\Service\UserAnonymization;

/** Native identity adapter, only registered when the GDPR contract exists. */
final readonly class UserRetentionOwner implements AccountRetentionOwner
{
    public function __construct(private InactiveUsers $users, private UserAnonymization $identity)
    {
    }

    public function maximumId(): int
    {
        return $this->users->maximumId();
    }

    public function batch(int $afterId, int $maximumId, \DateTimeImmutable $before, \DateTimeImmutable $now, int $limit): array
    {
        return array_map(static fn (array $row): InactivityCandidate => new InactivityCandidate(
            $row['id'], $row['email'], $row['language'], $row['activity'],
        ), $this->users->batch($afterId, $maximumId, $before, $now, $limit));
    }

    public function treat(InactivityCandidate $candidate, string $mode, \DateTimeImmutable $now, callable $authorize): bool
    {
        return match ($mode) {
            'anonymize' => $this->identity->anonymize($candidate->id, $candidate->email, $candidate->activity, $now, $authorize),
            'delete' => $this->identity->deleteInactive($candidate->id, $candidate->email, $candidate->activity, $now, $authorize),
            default => throw new \InvalidArgumentException('Unknown account treatment mode.'),
        };
    }
}
