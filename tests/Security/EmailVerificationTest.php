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

final class EmailVerificationTest extends ApiTestCase
{
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testRegistrationSendsVerificationEmail(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'body' => json_encode([
                'email' => 'newuser@example.com',
                'plainPassword' => 'Str0ng!Pass#2024',
            ]),
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'newuser@example.com');
        self::assertEmailSubjectContains($email, 'Confirmez votre adresse e-mail');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'newuser@example.com']);
        self::assertNotNull($user);
        self::assertFalse($user->isEmailVerified());
        self::assertNotNull($user->getEmailVerificationToken());
        self::assertNotNull($user->getEmailVerificationTokenExpiresAt());
        self::assertGreaterThan(new \DateTimeImmutable(), $user->getEmailVerificationTokenExpiresAt());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testLoginBlockedForUnverifiedUser(): void
    {
        $client = self::createClient();

        UserFactory::createOne([
            'email' => 'unverified@example.com',
            'emailVerified' => false,
        ]);

        $client->request('POST', '/api/login', [
            'json' => [
                'email' => 'unverified@example.com',
                'password' => 'P@ssw0rd123!',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertJsonContains(['message' => "Votre adresse e-mail n'a pas encore été vérifiée."]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testVerifyEmailWithValidToken(): void
    {
        $client = self::createClient();

        UserFactory::new()->withValidToken('validtoken123abc')->create([
            'email' => 'toverify@example.com',
        ]);

        $client->request('GET', '/api/verify-email?token=validtoken123abc');

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['message' => 'Adresse e-mail vérifiée avec succès.']);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'toverify@example.com']);
        self::assertNotNull($user);
        self::assertTrue($user->isEmailVerified());
        self::assertNull($user->getEmailVerificationToken());
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testVerifyEmailWithInvalidToken(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/verify-email?token=doesnotexist');

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains(['message' => 'Token invalide ou expiré.']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testVerifyEmailWithExpiredToken(): void
    {
        $client = self::createClient();

        UserFactory::new()->withExpiredToken('expiredtoken456')->create([
            'email' => 'expired@example.com',
        ]);

        $client->request('GET', '/api/verify-email?token=expiredtoken456');

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains(['message' => 'Token invalide ou expiré.']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testVerifyEmailWithMissingToken(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/verify-email');

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains(['message' => 'Token manquant.']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testLoginSucceedsAfterVerification(): void
    {
        $client = self::createClient();

        UserFactory::new()->withValidToken('myverifytoken')->create([
            'email' => 'verified@example.com',
        ]);

        $client->request('GET', '/api/verify-email?token=myverifytoken');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/login', [
            'json' => [
                'email' => 'verified@example.com',
                'password' => 'P@ssw0rd123!',
            ],
        ]);

        self::assertResponseIsSuccessful();
    }
}
