<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\JwtCookieSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

final class JwtCookieSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = JwtCookieSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(Events::AUTHENTICATION_SUCCESS, $events);
        self::assertSame('onAuthenticationSuccess', $events[Events::AUTHENTICATION_SUCCESS]);
    }

    /**
     * @param array<string, mixed> $initialData
     */
    #[DataProvider('invalidTokenProvider')]
    public function testOnAuthenticationSuccessDoesNothingIfTokenIsMissingOrInvalid(array $initialData): void
    {
        // 1. Instanciation du Subscriber
        $subscriber = new JwtCookieSubscriber('auth_token', true, 'lax', '/', '', 3600);

        // 2. Préparation de l'événement LexikJWT
        $user = $this->createStub(UserInterface::class);

        $response = new Response();
        $event = new AuthenticationSuccessEvent($initialData, $user, $response);

        // 3. Exécution
        $subscriber->onAuthenticationSuccess($event);

        // 4. Assertions : Les données ne doivent pas avoir changé, aucun cookie ne doit être créé
        self::assertSame($initialData, $event->getData(), 'Le tableau de données ne doit pas être altéré.');
        self::assertCount(0, $response->headers->getCookies(), 'Aucun cookie ne doit être injecté.');
    }

    public static function invalidTokenProvider(): \Generator
    {
        yield 'Token manquant' => [[]];
        yield 'Token null' => [['token' => null]];
        yield 'Token chaîne vide' => [['token' => '']];
        yield 'Token type invalide (entier)' => [['token' => 12345]];
    }

    public function testOnAuthenticationSuccessSetsCookieAndRemovesTokenWithEmptyDomain(): void
    {
        $subscriber = new JwtCookieSubscriber('auth_token', true, 'lax', '/api', '', 3600);

        $user = $this->createStub(UserInterface::class);
        $response = new Response();

        // On simule le payload généré par LexikJWT
        $event = new AuthenticationSuccessEvent([
            'token' => 'header.payload.signature',
            'user' => 'alice@example.com',
        ], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        // 1. Vérification du corps de la réponse (Json)
        $data = $event->getData();
        self::assertArrayNotHasKey('token', $data, 'Le token doit être retiré pour éviter toute fuite XSS.');
        self::assertArrayHasKey('user', $data, 'Les autres données doivent être conservées.');

        // 2. Vérification du Cookie
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        $cookie = $cookies[0];
        self::assertSame('auth_token', $cookie->getName());
        self::assertSame('header.payload.signature', $cookie->getValue());
        self::assertSame('/api', $cookie->getPath());
        self::assertNull($cookie->getDomain(), 'Un domaine vide dans le paramétrage doit donner un domaine null dans le cookie.');
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
        // On s'assure que l'expiration est cohérente (avec une marge de 2 secondes d'exécution)
        self::assertGreaterThanOrEqual(time() + 3598, $cookie->getExpiresTime());
    }

    public function testOnAuthenticationSuccessSetsCookieWithSpecificDomain(): void
    {
        $subscriber = new JwtCookieSubscriber('auth_token', true, 'strict', '/', 'api.example.com', 3600);

        $user = $this->createStub(UserInterface::class);

        $response = new Response();
        $event = new AuthenticationSuccessEvent(['token' => 'jwt_token'], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        self::assertSame('api.example.com', $cookies[0]->getDomain(), 'Le domaine explicite doit être respecté.');
    }
}
