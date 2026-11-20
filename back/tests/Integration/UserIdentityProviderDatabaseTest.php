<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use SymSvel\Module\Auth\Token\TokenSubject;
use SymSvel\Module\Users\Auth\UserIdentityProvider;
use SymSvel\Module\Users\Entity\User;

final class UserIdentityProviderDatabaseTest extends TestCase
{
    public function testAppIdentityFromPersistedUserAndDeletion(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($connection, $config);

        try {
            (new SchemaTool($em))->createSchema([$em->getClassMetadata(User::class)]);
            $user = new User('user@example.test', password_hash('correct-password', PASSWORD_ARGON2ID));
            $em->persist($user);
            $em->flush();
            $id = $user->getId();
            self::assertNotNull($id);
            $em->clear();

            $provider = new UserIdentityProvider($em);
            self::assertNull($provider->authenticate('bo', 'user@example.test', 'correct-password'));
            self::assertNull($provider->authenticate('app', 'user@example.test', 'wrong-password'));
            self::assertNull($provider->authenticate('app', 'user@example.test', 'correct-password'));
            self::assertFalse($provider->isActive(new TokenSubject('app', $id)));
            $connection->executeStatement(
                'UPDATE symsvel_users SET email_verified_at = ? WHERE id = ?',
                ['2026-11-16 10:00:00', $id],
            );
            $em->clear();
            self::assertEquals(new TokenSubject('app', $id), $provider->authenticate('app', 'user@example.test', 'correct-password'));
            self::assertTrue($provider->isActive(new TokenSubject('app', $id)));
            self::assertFalse($provider->isActive(new TokenSubject('bo', $id)));

            $stored = $em->find(User::class, $id);
            self::assertNotNull($stored);
            $em->remove($stored);
            $em->flush();
            $em->clear();

            self::assertFalse($provider->isActive(new TokenSubject('app', $id)));
            self::assertNull($provider->authenticate('app', 'user@example.test', 'correct-password'));
        } finally {
            $em->close();
            $connection->close();
        }
    }
}
