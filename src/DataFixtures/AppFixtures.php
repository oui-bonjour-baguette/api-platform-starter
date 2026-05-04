<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // ATTENTION : fixtures dev uniquement — ne jamais charger en production.
        $adminPassword = $_ENV['FIXTURE_ADMIN_PASSWORD'] ?? 'Admin@Dev!2024';
        $userPassword  = $_ENV['FIXTURE_USER_PASSWORD']  ?? 'User@Dev!2024';

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $adminPassword));
        $admin->setEmailVerified(true);
        $manager->persist($admin);

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, $userPassword));
        $user->setEmailVerified(true);
        $manager->persist($user);

        $manager->flush();
    }
}
