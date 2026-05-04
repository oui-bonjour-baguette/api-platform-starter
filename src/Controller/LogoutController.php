<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

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

    public function __invoke(): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);

        $response->headers->clearCookie(
            $this->cookieName,
            $this->path,
            $this->domain ?: null,
            $this->secure,
            true,
            $this->sameSite,
        );

        return $response;
    }
}
