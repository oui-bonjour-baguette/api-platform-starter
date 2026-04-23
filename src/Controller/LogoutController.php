<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Logout stateless : on efface le cookie côté navigateur.
 * Le JWT reste techniquement valide jusqu'à son `exp` — d'où un TTL court (cf. README).
 */
final readonly class LogoutController
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
    ) {
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function __invoke(): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie(
            name:     $this->cookieName,
            path:     $this->path,
            domain:   '' !== $this->domain ? $this->domain : null,
            secure:   $this->secure,
            httpOnly: true,
            sameSite: $this->sameSite,
        );

        return $response;
    }
}
