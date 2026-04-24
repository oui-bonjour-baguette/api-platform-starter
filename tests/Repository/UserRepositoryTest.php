<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Zenstruck\Foundry\Test\ResetDatabase;

final class UserRepositoryTest extends KernelTestCase
{
    // On purge la base de données entre chaque test
    use ResetDatabase;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // On force l'environnement de test (comme vu précédemment)
        self::bootKernel(['environment' => 'test']);

        // On récupère le vrai Repository depuis le conteneur de services
        $this->repository = static::getContainer()->get(UserRepository::class);
    }

    public function testUpgradePasswordThrowsExceptionForUnsupportedUser(): void
    {
        // 1. On crée un faux utilisateur ("Stub") qui n'est pas notre entité App\Entity\User
        $unsupportedUser = $this->createStub(PasswordAuthenticatedUserInterface::class);

        // 2. On s'attend à ce que l'exception soit levée
        $this->expectException(UnsupportedUserException::class);
        $this->expectExceptionMessage('Instances of');

        // 3. Exécution qui va déclencher l'erreur
        $this->repository->upgradePassword($unsupportedUser, 'new_hashed_password');
    }

    public function testUpgradePasswordSuccessfullyUpgradesAndFlushes(): void
    {
        // 1. On crée un vrai utilisateur en base de données via notre Factory
        $user = UserFactory::createOne(['password' => 'old_password']);

        // 2. On appelle la méthode à tester
        $this->repository->upgradePassword($user, 'new_super_secure_hash');

        // 3. Vérification en mémoire
        self::assertSame('new_super_secure_hash', $user->getPassword());

        // On vide l'Identity Map de Doctrine pour forcer un re-chargement depuis la vraie base de données.
        // Cela prouve que le $this->getEntityManager()->flush() a bien fait son travail.
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();

        /** @var User $reloadedUser */
        $reloadedUser = $this->repository->find($user->getId());

        self::assertSame('new_super_secure_hash', $reloadedUser->getPassword());
    }
}
