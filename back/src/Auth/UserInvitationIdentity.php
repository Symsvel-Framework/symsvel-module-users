<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Auth;

use SymSvel\Module\Auth\Identity\AppInvitationIdentity;
use SymSvel\Module\Auth\Token\TokenSubject;

/** Auth owns the token; Users owns the atomic account change. */
final readonly class UserInvitationIdentity implements AppInvitationIdentity
{
    public function __construct(private UserPasswordResetIdentity $users) {}

    public function activateInvitation(
        int $userId,
        string $emailHash,
        string $passwordHash,
        \DateTimeImmutable $now,
    ): bool {
        $subject = new TokenSubject('app', $userId);
        // Both changes are performed on the host DBAL connection inside the
        // Auth invitation transaction. A failed password write rolls both back.
        return $this->users->confirmEmail($subject, $emailHash, $now)
            && $this->users->replacePassword($subject, $passwordHash);
    }
}
