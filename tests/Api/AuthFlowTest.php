<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bout-en-bout : register → login (cookie httpOnly, pas de token dans le body)
 * → GET /api/me via cookie → logout → 401.
 */
final class AuthFlowTest extends ApiTestCase
{
    private const EMAIL = 'alice@example.com';
    private const PASSWORD = 'supersecret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabase();
    }

    public function testFullAuthFlow(): void
    {
        $client = self::createClient();

        // 1) Register ------------------------------------------------------
        $client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => self::EMAIL,
                'plainPassword' => self::PASSWORD,
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertJsonContains(['email' => self::EMAIL]);
        $body = $client->getResponse()->toArray(false);
        self::assertArrayNotHasKey('password', $body);
        self::assertArrayNotHasKey('plainPassword', $body);
        self::assertArrayNotHasKey('token', $body);

        // 2) Login ---------------------------------------------------------
        $client->request('POST', '/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => self::EMAIL,
                'password' => self::PASSWORD,
            ],
        ]);
        self::assertResponseIsSuccessful();

        $loginBody = $client->getResponse()->toArray(false);
        self::assertArrayNotHasKey('token', $loginBody, 'Le JWT doit rester confiné au cookie.');

        $setCookie = $client->getResponse()->getHeaders(false)['set-cookie'][0] ?? '';
        self::assertStringContainsString('auth_token=', $setCookie);
        self::assertStringContainsStringIgnoringCase('httponly', $setCookie);
        self::assertStringContainsStringIgnoringCase('samesite=lax', $setCookie);

        // 3) GET /api/me avec cookie ---------------------------------------
        // HttpClient d'ApiTestCase ne gère pas le jar automatiquement : on rejoue l'en-tête.
        $cookieHeader = self::extractCookie($setCookie);

        $client->request('GET', '/api/me', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'Cookie' => $cookieHeader,
            ],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => self::EMAIL]);

        // 4) GET /api/me sans cookie --------------------------------------
        $client->request('GET', '/api/me', [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        // 5) Logout --------------------------------------------------------
        $client->request('POST', '/api/logout', [
            'headers' => ['Cookie' => $cookieHeader],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $clearCookie = $client->getResponse()->getHeaders(false)['set-cookie'][0] ?? '';
        self::assertStringContainsString('auth_token=', $clearCookie);
        // Le header de suppression a une date d'expiration dans le passé (1970).
        self::assertStringContainsString('1970', $clearCookie);
    }

    private static function extractCookie(string $setCookieHeader): string
    {
        // "auth_token=<jwt>; expires=...; path=/; httponly; samesite=lax"
        [$pair] = explode(';', $setCookieHeader, 2);

        return trim($pair);
    }

    private function resetDatabase(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM '.User::class)->execute();
    }
}
