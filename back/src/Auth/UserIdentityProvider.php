<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Auth;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use SymSvel\Module\Auth\Identity\PasswordPrincipal;
use SymSvel\Module\Auth\Identity\ContextOwnedIdentity;
use SymSvel\Module\Auth\Identity\TransactionalPrincipalStatus;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Users\Entity\User;

/** App-only identity adapter; the Users module is not required by BO-only installs. */
final readonly class UserIdentityProvider implements PasswordPrincipal, TransactionalPrincipalStatus, ContextOwnedIdentity
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

    public function lockActive(TokenSubject $subject): bool
    {
        if ($subject->context !== 'app') {
            return false;
        }
        $db = $this->entityManager->getConnection();
        if (!$db->isTransactionActive()) {
            throw new \LogicException('Identity guard requires an active transaction.');
        }
        $sql = 'SELECT id FROM symsvel_users WHERE id = ? AND enabled = ? AND email_verified_at IS NOT NULL AND anonymized_at IS NULL';
        if ($db->getDatabasePlatform() instanceof SQLitePlatform) {
            $db->executeStatement('UPDATE symsvel_users SET id = id WHERE id = ?', [$subject->id]);
        } else {
            $sql .= ' FOR UPDATE';
        }
        return $db->fetchOne($sql, [$subject->id, true], [ParameterType::INTEGER, ParameterType::BOOLEAN]) !== false;
    }
}
