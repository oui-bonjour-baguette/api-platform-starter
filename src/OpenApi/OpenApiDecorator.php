<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\OpenApi;

final class OpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        $messageSchema = new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
            ],
        ]);

        $paths->addPath('/api/forgot-password', new Model\PathItem(
            post: new Model\Operation(
                operationId: 'forgotPassword',
                tags: ['Authentification'],
                summary: 'Demander la réinitialisation du mot de passe',
                description: "Envoie un email de réinitialisation si le compte existe. Retourne toujours 200 pour éviter l'énumération des comptes.",
                requestBody: new Model\RequestBody(
                    description: 'Adresse e-mail du compte',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email'],
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'format' => 'email',
                                        'example' => 'user@example.com',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                    required: true,
                ),
                responses: new \ArrayObject([
                    '200' => [
                        'description' => 'Email envoyé (ou compte inexistant — anti-énumération)',
                        'content' => ['application/json' => ['schema' => $messageSchema]],
                    ],
                    '422' => [
                        'description' => 'Adresse e-mail invalide',
                        'content' => ['application/json' => ['schema' => $messageSchema]],
                    ],
                ]),
            ),
        ));

        $paths->addPath('/api/reset-password', new Model\PathItem(
            post: new Model\Operation(
                operationId: 'resetPassword',
                tags: ['Authentification'],
                summary: 'Réinitialiser le mot de passe',
                description: 'Réinitialise le mot de passe à l\'aide du token reçu par email. Le token a une durée de vie d\'1 heure.',
                requestBody: new Model\RequestBody(
                    description: 'Token de réinitialisation et nouveau mot de passe',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['token', 'password'],
                                'properties' => [
                                    'token' => [
                                        'type' => 'string',
                                        'example' => 'a3f8c2...',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'format' => 'password',
                                        'minLength' => 8,
                                        'example' => 'N3wStr0ng!Pass',
                                        'description' => 'Min. 8 caractères, majuscule + minuscule + chiffre + caractère spécial',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                    required: true,
                ),
                responses: new \ArrayObject([
                    '200' => [
                        'description' => 'Mot de passe réinitialisé avec succès',
                        'content' => ['application/json' => ['schema' => $messageSchema]],
                    ],
                    '400' => [
                        'description' => 'Token manquant, invalide ou expiré',
                        'content' => ['application/json' => ['schema' => $messageSchema]],
                    ],
                    '422' => [
                        'description' => 'Mot de passe invalide (contraintes non respectées)',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string'],
                                        'errors' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ),
        ));

        return $openApi;
    }
}
