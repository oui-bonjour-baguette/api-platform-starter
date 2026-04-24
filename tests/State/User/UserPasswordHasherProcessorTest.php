<?php

declare(strict_types=1);

namespace App\Tests\State\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\State\User\UserPasswordHasherProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordHasherProcessorTest extends TestCase
{
    public function testProcessHashesPasswordAndErasesCredentials(): void
    {
        $user = new User();
        $user->setPlainPassword('Secret123!');
        $operation = new Post();

        $persistProcessorMock = $this->createMock(ProcessorInterface::class);
        $persistProcessorMock->expects(self::once())
            ->method('process')
            ->with($user, $operation, [], [])
            ->willReturn($user);

        $hasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $hasherMock->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'Secret123!')
            ->willReturn('hashed_password_string');

        $processor = new UserPasswordHasherProcessor($persistProcessorMock, $hasherMock);
        $result = $processor->process($user, $operation);

        self::assertSame('hashed_password_string', $user->getPassword(), 'Le mot de passe hashé doit être assigné.');
        self::assertNull($user->getPlainPassword(), 'Le mot de passe en clair doit être effacé.');
        self::assertSame($user, $result, 'Le processeur doit retourner l\'entité User.');
    }

    public function testProcessDoesNothingWhenPlainPasswordIsNull(): void
    {
        $user = new User();
        $user->setPlainPassword(null);
        $operation = new Post();

        $persistProcessorMock = $this->createMock(ProcessorInterface::class);
        $persistProcessorMock->expects(self::once())
            ->method('process')
            ->willReturn($user);

        $hasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $hasherMock->expects(self::never())->method('hashPassword');

        $processor = new UserPasswordHasherProcessor($persistProcessorMock, $hasherMock);
        $processor->process($user, $operation);
    }

    public function testProcessDoesNothingWhenPlainPasswordIsEmptyString(): void
    {
        $user = new User();
        $user->setPlainPassword('');
        $operation = new Post();

        $persistProcessorMock = $this->createMock(ProcessorInterface::class);
        $persistProcessorMock->expects(self::once())
            ->method('process')
            ->willReturn($user);

        $hasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $hasherMock->expects(self::never())->method('hashPassword');

        $processor = new UserPasswordHasherProcessor($persistProcessorMock, $hasherMock);
        $processor->process($user, $operation);
    }
}
