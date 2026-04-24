<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\LogoutController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class LogoutControllerTest extends TestCase
{
    public function testInvokeClearsCookieWithEmptyDomain(): void
    {
        $controller = new LogoutController(
            cookieName: 'auth_token',
            secure: true,
            sameSite: 'lax',
            path: '/',
            domain: ''
        );

        $response = $controller();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        $cookie = $cookies[0];
        self::assertSame('auth_token', $cookie->getName());
        self::assertTrue($cookie->isCleared());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertNull($cookie->getDomain());
    }

    public function testInvokeClearsCookieWithSpecificDomain(): void
    {
        $controller = new LogoutController(
            cookieName: 'auth_token',
            secure: true,
            sameSite: 'strict',
            path: '/api',
            domain: 'api.example.com'
        );

        $response = $controller();

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        $cookie = $cookies[0];
        self::assertSame('api.example.com', $cookie->getDomain());
    }
}
