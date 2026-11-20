<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Auth;

use Doctrine\ORM\EntityManagerInterface;
use SymSvel\Module\Auth\Identity\PasswordPrincipal;
use SymSvel\Module\Auth\Identity\ContextOwnedIdentity;
use SymSvel\Module\Auth\Identity\PrincipalStatus;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Users\Entity\User;

/** App-only identity adapter; the Users module is not required by BO-only installs. */
final readonly class UserIdentityProvider implements PasswordPrincipal, PrincipalStatus, ContextOwnedIdentity
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function authContext(): string
    {
        return 'app';
    }

    public function authenticate(string $context, string $identifier, string $password): ?TokenSubject
    {
        if ($context !== 'app' || filter_var($identifier, FILTER_VALIDATE_EMAIL) === false || strlen($identifier) > 254) {
            return null;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
        if (!$user instanceof User || !$user->isEnabled() || $user->getEmailVerifiedAt() === null || !password_verify($password, $user->getPasswordHash())) {
            return null;
        }

        $id = $user->getId();

        return $id === null ? null : new TokenSubject('app', $id);
    }

    public function isActive(TokenSubject $subject): bool
    {
        if ($subject->context !== 'app') {
            return false;
        }

        $user = $this->entityManager->find(User::class, $subject->id);

        return $user instanceof User && $user->isEnabled() && $user->getEmailVerifiedAt() !== null;
    }
}
