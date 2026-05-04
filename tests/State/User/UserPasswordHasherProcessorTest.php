<?php

declare(strict_types=1);

namespace App\Tests\State\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Security\EmailVerificationService;
use App\State\User\UserPasswordHasherProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordHasherProcessorTest extends TestCase
{
    private function makeProcessor(
        ProcessorInterface $persistProcessorMock,
        UserPasswordHasherInterface $hasherMock,
        ?EmailVerificationService $emailServiceMock = null,
    ): UserPasswordHasherProcessor {
        $emailServiceMock ??= $this->createMock(EmailVerificationService::class);

        return new UserPasswordHasherProcessor($persistProcessorMock, $hasherMock, $emailServiceMock);
    }

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

        $emailServiceMock = $this->createMock(EmailVerificationService::class);
        $emailServiceMock->expects(self::once())->method('prepareToken')->with($user);
        $emailServiceMock->expects(self::once())->method('sendVerificationEmail')->with($user);

        $processor = $this->makeProcessor($persistProcessorMock, $hasherMock, $emailServiceMock);
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

        $processor = $this->makeProcessor($persistProcessorMock, $hasherMock);
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

        $processor = $this->makeProcessor($persistProcessorMock, $hasherMock);
        $processor->process($user, $operation);
    }
}
