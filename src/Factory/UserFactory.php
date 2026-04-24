<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    /**
     * Injection du service de hashage natif de Symfony.
     */
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    /**
     * Définit les valeurs par défaut pour la création d'un User.
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            // Utilisation de unique() pour éviter les collisions avec ton UniqueConstraint
            'email' => self::faker()->unique()->safeEmail(),

            // Mot de passe statique respectant ta Regex stricte.
            // On le passe dans plainPassword, le hook s'occupera du hashage.
            'plainPassword' => 'P@ssw0rd123!',

            'roles' => [],
        ];
    }

    /**
     * Intercepte l'entité juste après sa création en mémoire pour hasher le mot de passe.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function (User $user): void {
                if ($plainPassword = $user->getPlainPassword()) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);

                    // Sécurité : on efface la donnée sensible de l'entité
                    $user->eraseCredentials();
                }
            })
        ;
    }

    /**
     * Custom state : permet de créer facilement un Administrateur dans les tests.
     */
    public function asAdmin(): static
    {
        return $this->with([
            'roles' => ['ROLE_ADMIN'],
        ]);
    }
}
