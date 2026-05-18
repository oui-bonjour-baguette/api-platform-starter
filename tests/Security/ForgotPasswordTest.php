<?php

declare(strict_types=1);

namespace App\Tests\Security;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ForgotPasswordTest extends ApiTestCase
{
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test']);
    }

    /**
     * @throws TransportExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|DecodingExceptionInterface|ClientExceptionInterface
     */
    public function testForgotPasswordWithKnownEmailSendsEmail(): void
    {
        $client = self::createClient();

        UserFactory::new()->asVerified()->create(['email' => 'known@example.com']);

        $client->request('POST', '/api/forgot-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['email' => 'known@example.com']),
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.']);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'known@example.com');
        self::assertEmailSubjectContains($email, 'Réinitialisez votre mot de passe');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'known@example.com']);
        self::assertNotNull($user->getPasswordResetToken());
        self::assertNotNull($user->getPasswordResetTokenExpiresAt());
        self::assertGreaterThan(new \DateTimeImmutable(), $user->getPasswordResetTokenExpiresAt());
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testForgotPasswordWithUnknownEmailReturns200WithoutEmail(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/forgot-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['email' => 'nobody@example.com']),
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.']);
        self::assertEmailCount(0);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testForgotPasswordWithInvalidEmailReturns422(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/forgot-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['email' => 'not-an-email']),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['message' => 'Adresse e-mail invalide.']);
    }

    /**
     * @throws TransportExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|DecodingExceptionInterface|ClientExceptionInterface
     */
    public function testResetPasswordWithValidToken(): void
    {
        $client = self::createClient();

        UserFactory::new()->withValidPasswordResetToken('validresettoken123')->create([
            'email' => 'reset@example.com',
        ]);

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'token' => 'validresettoken123',
                'password' => 'N3wStr0ng!Pass',
            ]),
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['message' => 'Mot de passe réinitialisé avec succès.']);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'reset@example.com']);
        self::assertNotNull($user);
        self::assertNull($user->getPasswordResetToken());
        self::assertNull($user->getPasswordResetTokenExpiresAt());
    }

    /**
     * @throws TransportExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|DecodingExceptionInterface|ClientExceptionInterface
     */
    public function testCanLoginWithNewPasswordAfterReset(): void
    {
        $client = self::createClient();

        UserFactory::new()->withValidPasswordResetToken('loginafterreset')->create([
            'email' => 'logintest@example.com',
        ]);

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'token' => 'loginafterreset',
                'password' => 'N3wStr0ng!Pass',
            ]),
        ]);
        self::assertResponseStatusCodeSame(200);

        $client->request('POST', '/api/login', [
            'json' => [
                'email' => 'logintest@example.com',
                'password' => 'N3wStr0ng!Pass',
            ],
        ]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testResetPasswordWithExpiredTokenReturns400(): void
    {
        $client = self::createClient();

        UserFactory::new()->withExpiredPasswordResetToken('expiredresettoken')->create([
            'email' => 'expired@example.com',
        ]);

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'token' => 'expiredresettoken',
                'password' => 'N3wStr0ng!Pass',
            ]),
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains(['message' => 'Token invalide ou expiré.']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testResetPasswordWithInvalidTokenReturns400(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'token' => 'completelywrongtoken',
                'password' => 'N3wStr0ng!Pass',
            ]),
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains(['message' => 'Token invalide ou expiré.']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testResetPasswordWithMissingTokenReturns400(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['password' => 'N3wStr0ng!Pass']),
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains(['message' => 'Token manquant.']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testResetPasswordWithWeakPasswordReturns422(): void
    {
        $client = self::createClient();

        UserFactory::new()->withValidPasswordResetToken('weakpasstoken')->create([
            'email' => 'weakpass@example.com',
        ]);

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'token' => 'weakpasstoken',
                'password' => 'weak',
            ]),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['message' => 'Mot de passe invalide.']);
    }
}
