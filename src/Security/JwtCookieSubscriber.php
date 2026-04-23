<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Sur succès d'authentification JSON login :
 *  1) retire le JWT du corps de la réponse (aucune fuite dans le JSON),
 *  2) pose un cookie httpOnly qui transporte le token.
 */
final readonly class JwtCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%env(JWT_COOKIE_NAME)%')]
        private string $cookieName,
        #[Autowire('%env(bool:JWT_COOKIE_SECURE)%')]
        private bool $secure,
        #[Autowire('%env(JWT_COOKIE_SAMESITE)%')]
        private string $sameSite,
        #[Autowire('%env(JWT_COOKIE_PATH)%')]
        private string $path,
        #[Autowire('%env(JWT_COOKIE_DOMAIN)%')]
        private string $domain,
        #[Autowire('%env(int:JWT_TTL)%')]
        private int $ttl,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $token = $data['token'] ?? null;

        if (!\is_string($token) || '' === $token) {
            return;
        }

        unset($data['token']);
        $event->setData($data);

        $response = $event->getResponse();
        $response->headers->setCookie(Cookie::create(
            name:     $this->cookieName,
            value:    $token,
            expire:   time() + $this->ttl,
            path:     $this->path,
            domain:   '' !== $this->domain ? $this->domain : null,
            secure:   $this->secure,
            httpOnly: true,
            raw:      false,
            sameSite: $this->sameSite,
        ));
    }
}
