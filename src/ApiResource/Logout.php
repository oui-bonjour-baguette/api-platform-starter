<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Controller\LogoutController;

#[ApiResource(
    shortName: 'User',
    operations: [
        new Post(
            uriTemplate: '/logout',
            status: 204,
            controller: LogoutController::class,
            openapi: new OpenApiOperation(
                summary: 'Déconnexion',
                description: 'Invalide la session en supprimant le cookie JWT HttpOnly.',
            ),
            output: false,
            read: false,
            deserialize: false,
            write: false,
            serialize: false,
        ),
    ]
)]
final class Logout
{
}
