<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    public function testCheckPreAuthThrowsForUnverifiedUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage("Votre adresse e-mail n'a pas encore été vérifiée.");

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPreAuthPassesForVerifiedUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setEmailVerified(true);

        $this->checker->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }

    public function testCheckPreAuthIgnoresNonUserInstance(): void
    {
        $foreignUser = $this->createMock(UserInterface::class);

        $this->checker->checkPreAuth($foreignUser);

        $this->addToAssertionCount(1);
    }

    public function testCheckPostAuthDoesNothing(): void
    {
        $user = new User();

        $this->checker->checkPostAuth($user);

        $this->addToAssertionCount(1);
    }
}
