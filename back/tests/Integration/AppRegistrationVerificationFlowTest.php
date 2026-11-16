<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use SymSvel\Module\Auth\Controller\AppAuthenticationController;
use SymSvel\Module\Auth\Controller\AppEmailVerificationController;
use SymSvel\Module\Auth\Controller\AppPasswordResetController;
use SymSvel\Module\Auth\Identity\AuthenticatedTokenResolver;
use SymSvel\Module\Auth\Identity\ContextEmailVerificationIdentity;
use SymSvel\Module\Auth\Identity\ContextIdentityProvider;
use SymSvel\Module\Auth\Identity\ContextPasswordResetIdentity;
use SymSvel\Module\Auth\Identity\EmailVerificationCompletion;
use SymSvel\Module\Auth\Identity\EmailVerificationDispatch;
use SymSvel\Module\Auth\Identity\PasswordLogin;
use SymSvel\Module\Auth\Identity\PasswordResetLimiter;
use SymSvel\Module\Auth\Identity\PasswordResetDispatch;
use SymSvel\Module\Auth\Identity\PasswordResetCompletion;
use SymSvel\Module\Auth\Mail\AuthMailTemplateProvider;
use SymSvel\Module\Auth\Schema\AuthTokenSchema;
use SymSvel\Module\Auth\Schema\EmailVerificationSchema;
use SymSvel\Module\Auth\Schema\PasswordResetLimitSchema;
use SymSvel\Module\Auth\Schema\PasswordResetSchema;
use SymSvel\Module\Auth\Service\AuthSettingDefinitionProvider;
use SymSvel\Module\Auth\Service\EmailVerificationUrlResolver;
use SymSvel\Module\Auth\Service\PasswordResetUrlResolver;
use SymSvel\Module\Auth\Token\AccessTokenVerifier;
use SymSvel\Module\Auth\Token\ContextSessionLogout;
use SymSvel\Module\Auth\Token\DbalEmailVerificationStore;
use SymSvel\Module\Auth\Token\DbalPasswordResetStore;
use SymSvel\Module\Auth\Token\DbalTokenStore;
use SymSvel\Module\Auth\Token\RefreshTokenGuard;
use SymSvel\Module\Auth\Token\TokenFactory;
use SymSvel\Module\Auth\Token\TokenIssuer;
use SymSvel\Module\Auth\Token\TokenRotation;
use SymSvel\Module\Mails\Mail\ConfiguredMailSender;
use SymSvel\Module\Mails\Mail\MailSender;
use SymSvel\Module\Mails\Mail\MailTemplateRegistry;
use SymSvel\Module\Mails\Mail\RegisteredMailRenderer;
use SymSvel\Module\Mails\Service\MailsSettingDefinitionProvider;
use SymSvel\Module\Settings\Entity\Setting;
use SymSvel\Module\Settings\Repository\SettingRepository;
use SymSvel\Module\Settings\Service\SettingKeyQualifier;
use SymSvel\Module\Settings\Service\SettingManager;
use SymSvel\Module\Settings\Service\SettingRegistry;
use SymSvel\Module\Settings\Service\SettingSecretCipher;
use SymSvel\Module\Users\Auth\UserIdentityProvider;
use SymSvel\Module\Users\Auth\UserPasswordResetIdentity;
use SymSvel\Module\Users\Controller\UserRegistrationController;
use SymSvel\Module\Users\Entity\User;
use SymSvel\Module\Users\Service\UserRegistration;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class AppRegistrationVerificationFlowTest extends TestCase
{
    public function testRegistrationConfirmLoginRefreshAndLogout(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname((new \ReflectionClass(User::class))->getFileName())], true,
        );
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager($db, $config);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(User::class)]);

        $schema = new Schema();
        AuthTokenSchema::apply($schema);
        EmailVerificationSchema::apply($schema);
        PasswordResetLimitSchema::apply($schema);
        PasswordResetSchema::apply($schema);
        foreach ($schema->toSql($db->getDatabasePlatform()) as $sql) {
            $db->executeStatement($sql);
        }

        $settingsConfig = ORMSetup::createAttributeMetadataConfiguration(
            [dirname((new \ReflectionClass(Setting::class))->getFileName())], true,
        );
        $settingsConfig->enableNativeLazyObjects(true);
        $settingsDb = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $settingsEm = new EntityManager($settingsDb, $settingsConfig);
        (new SchemaTool($settingsEm))->createSchema([$settingsEm->getClassMetadata(Setting::class)]);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(Setting::class)->willReturn($settingsEm);
        $settings = new SettingRegistry([]);
        $settings->registerOwned(new AuthSettingDefinitionProvider(), 'auth', new SettingKeyQualifier());
        $settings->registerOwned(new MailsSettingDefinitionProvider(), 'mails', new SettingKeyQualifier());
        $settingManager = new SettingManager(
            $settings, new SettingRepository($registry), $settingsEm,
            new SettingSecretCipher('integration-secret-for-users'),
        );
        $settingManager->set('auth.app_reset_url', 'https://app.example.test/reset-password');
        $settingManager->set('mails.sender_email', 'sender@example.test');
        $settingManager->flush();

        $sent = new \ArrayObject();
        $mailFailure = false;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(
            static function (RawMessage $message) use ($sent, &$mailFailure): void {
                if ($mailFailure) {
                    throw new \RuntimeException('SMTP unavailable');
                }
                $sent->append($message);
            },
        );
        $errors = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(
            static function (string $message, array $context = []) use (&$errors): void {
                self::assertInstanceOf(\Exception::class, $context['exception'] ?? null);
                $errors[] = $message;
            },
        );
        $loader = new FilesystemLoader();
        $loader->addPath(dirname((new \ReflectionClass(AuthMailTemplateProvider::class))->getFileName(), 3).'/templates', 'Auth');
        $renderer = new RegisteredMailRenderer(
            new MailTemplateRegistry([new AuthMailTemplateProvider()]), new Environment($loader, ['autoescape' => 'name']),
        );
        $owner = new UserPasswordResetIdentity($db);
        $limiter = new PasswordResetLimiter($db, 'integration-secret-for-limits');
        $workspace = sys_get_temp_dir().'/symsvel-users-auth-domain-'.bin2hex(random_bytes(8));
        mkdir($workspace.'/.symsvel', 0700, true);
        mkdir($workspace.'/backend', 0700);
        $projectMetadata = $workspace.'/.symsvel/project.json';
        file_put_contents($projectMetadata, '{"schema":1,"local_domain":"example.test"}');
        $dispatch = new EmailVerificationDispatch(
            $limiter, new EmailVerificationUrlResolver($settingManager, $workspace.'/backend'),
            new ContextPasswordResetIdentity([$owner]),
            new ContextEmailVerificationIdentity([$owner]), new DbalEmailVerificationStore($db),
            $renderer, new ConfiguredMailSender(new MailSender($mailer), $settingManager),
        );
        $register = new UserRegistrationController(new UserRegistration($db, $limiter), $dispatch, $logger);
        $verify = new AppEmailVerificationController(
            $dispatch, new EmailVerificationCompletion(
                $db, new DbalEmailVerificationStore($db),
                new ContextEmailVerificationIdentity([$owner]),
            ),
            $logger,
        );
        $reset = new AppPasswordResetController(
            new PasswordResetDispatch(
                $limiter, new PasswordResetUrlResolver($settingManager),
                new ContextPasswordResetIdentity([$owner]), new DbalPasswordResetStore($db),
                $renderer, new ConfiguredMailSender(new MailSender($mailer), $settingManager),
            ),
            new PasswordResetCompletion(
                $db, new DbalPasswordResetStore($db),
                new ContextPasswordResetIdentity([$owner]), new DbalTokenStore($db),
            ),
            $logger,
        );
        $principals = new ContextIdentityProvider([new UserIdentityProvider($em)]);
        $tokens = new DbalTokenStore($db);
        $sessions = new AppAuthenticationController(
            new PasswordLogin($principals, new TokenIssuer($tokens, new TokenFactory())),
            new TokenRotation($tokens, new TokenFactory(), new RefreshTokenGuard(), $principals),
            new AuthenticatedTokenResolver(new AccessTokenVerifier($tokens), $principals),
            new ContextSessionLogout($db),
        );

        try {
            $registration = $register(self::request('/apiopen/users/register', [
                'email' => ' person@example.test ', 'password' => 'correct-password-123', 'locale' => 'fr',
            ]));
            self::assertSame(202, $registration->getStatusCode());
            self::assertSame(1, $sent->count());
            self::assertNull($db->fetchOne('SELECT email_verified_at FROM symsvel_users') ?: null);
            self::assertSame(401, $sessions->login(self::request('/apiopen/auth/login', [
                'email' => 'person@example.test', 'password' => 'correct-password-123',
            ]))->getStatusCode());

            $message = $sent[0];
            self::assertInstanceOf(Email::class, $message);
            self::assertSame('person@example.test', $message->getTo()[0]->getAddress());
            self::assertSame(1, preg_match('~https://app\\.example\\.test/verify-email\\?token=([A-Za-z0-9_-]{43})~', (string) $message->getTextBody(), $matches));
            $raw = $matches[1];
            self::assertSame(200, $verify->complete(self::request('/apiopen/auth/email-verification/complete', [
                'token' => $raw,
            ]))->getStatusCode());
            self::assertSame(400, $verify->complete(self::request('/apiopen/auth/email-verification/complete', [
                'token' => $raw,
            ]))->getStatusCode());

            $em->clear();
            $login = $sessions->login(self::request('/apiopen/auth/login', [
                'email' => 'person@example.test', 'password' => 'correct-password-123',
            ]));
            self::assertSame(200, $login->getStatusCode());
            $body = json_decode((string) $login->getContent(), true, 8, JSON_THROW_ON_ERROR);
            $access = $body['access_token'];
            self::assertSame('app', json_decode(
                (string) $sessions->me(self::authorized('/api/auth/me', 'GET', $access))->getContent(),
                true, 8, JSON_THROW_ON_ERROR,
            )['context']);

            $refresh = $sessions->refresh(self::request('/apiopen/auth/refresh', [
                'refresh_token' => $body['refresh_token'],
            ]));
            self::assertSame(200, $refresh->getStatusCode());
            $rotated = json_decode((string) $refresh->getContent(), true, 8, JSON_THROW_ON_ERROR);
            self::assertSame(401, $sessions->me(self::authorized('/api/auth/me', 'GET', $access))->getStatusCode());
            self::assertSame(204, $sessions->logout(self::authorized('/api/auth/logout', 'POST', $rotated['access_token'], [
                'refresh_token' => $rotated['refresh_token'],
            ]))->getStatusCode());
            self::assertSame(401, $sessions->me(self::authorized('/api/auth/me', 'GET', $rotated['access_token']))->getStatusCode());

            self::assertSame(202, $register(self::request('/apiopen/users/register', [
                'email' => 'person@example.test', 'password' => 'other-password-123', 'locale' => 'fr',
            ]))->getStatusCode());
            self::assertSame(1, $sent->count());
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM symsvel_users'));

            // Broken SMTP must not distinguish a newly created/pending/verified
            // account from an unknown address through an HTTP 500.
            $mailFailure = true;
            $offlineRequest = self::request('/apiopen/users/register', [
                'email' => 'offline@example.test', 'password' => 'correct-password-456', 'locale' => 'fr',
            ]);
            $offlineRequest->server->set('REMOTE_ADDR', '192.0.2.90');
            $offline = $register($offlineRequest);
            self::assertSame(202, $offline->getStatusCode());
            self::assertSame('{"status":"accepted"}', $offline->getContent());
            self::assertSame(1, (int) $db->fetchOne(
                'SELECT COUNT(*) FROM symsvel_users WHERE email = ? AND email_verified_at IS NULL',
                ['offline@example.test'],
            ));

            $db->insert('symsvel_users', [
                'email' => 'resend@example.test',
                'password_hash' => password_hash('correct-password-789', PASSWORD_ARGON2ID),
                'enabled' => true,
                'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
                'email_verified_at' => null,
            ]);
            $resendRequest = self::request('/apiopen/auth/email-verification/request', [
                'email' => 'resend@example.test', 'locale' => 'fr',
            ]);
            $resendRequest->server->set('REMOTE_ADDR', '192.0.2.91');
            self::assertSame(202, $verify->request($resendRequest)->getStatusCode());

            $resetRequest = self::request('/apiopen/auth/password-reset/request', [
                'email' => 'person@example.test', 'locale' => 'fr',
            ]);
            $resetRequest->server->set('REMOTE_ADDR', '192.0.2.92');
            self::assertSame(202, $reset->request($resetRequest)->getStatusCode());
            self::assertSame(1, $sent->count());
            self::assertSame([
                'App signup confirmation dispatch failed.',
                'App email verification dispatch failed.',
                'App password reset dispatch failed.',
            ], $errors);
        } finally {
            $em->close();
            $settingsEm->close();
            $db->close();
            $settingsDb->close();
            @unlink($projectMetadata);
            rmdir($workspace.'/backend');
            rmdir($workspace.'/.symsvel');
            rmdir($workspace);
        }
    }

    private static function request(string $path, array $payload): Request
    {
        return Request::create($path, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '192.0.2.42',
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function authorized(string $path, string $method, string $token, array $payload = []): Request
    {
        return Request::create($path, $method, [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
