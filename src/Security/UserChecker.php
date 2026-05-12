<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[WithMonologChannel('security')]
final class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEmailVerified()) {
            $this->logger->warning('Login attempt blocked: Email not verified.', [
                'user_email' => $user->getEmail(),
                'user_id' => $user->getId(),
            ]);

            throw new CustomUserMessageAccountStatusException("Votre adresse e-mail n'a pas encore été vérifiée.");
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if ($user instanceof User) {
            $this->logger->info('User successfully authenticated.', [
                'user_email' => $user->getEmail(),
                'user_id' => $user->getId(),
            ]);
        }
    }
}
