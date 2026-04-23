<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

/**
 * Stub nécessaire pour que Symfony résolve la route /api/login
 * AVANT que le firewall ne traite le json_login. Le corps de la méthode
 * n'est jamais exécuté : le JsonLoginAuthenticator intercepte la requête
 * et renvoie la réponse (JWT en cookie via JwtCookieSubscriber).
 */
final readonly class LoginController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(): never
    {
        throw new \LogicException('Cette méthode ne devrait jamais être appelée — intercepted by json_login.');
    }
}
