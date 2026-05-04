<?php

declare(strict_types=1);

namespace App\State\User;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Security\EmailVerificationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Hashe `plainPassword` → `password` avant de déléguer la persistance
 * au processor Doctrine standard d'API Platform.
 *
 * @implements ProcessorInterface<User, User>
 */
final readonly class UserPasswordHasherProcessor implements ProcessorInterface
{

    /**
     * @param ProcessorInterface<User, User> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private ProcessorInterface $persistProcessor,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService $emailVerificationService,
    ) {
    }

    /**
     * @param User                 $data
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if (null !== $data->getPlainPassword() && '' !== $data->getPlainPassword()) {
            $data->setPassword($this->passwordHasher->hashPassword($data, $data->getPlainPassword()));
            $data->eraseCredentials();
        }

        $this->emailVerificationService->prepareToken($data);

        $persisted = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->emailVerificationService->sendVerificationEmail($persisted);

        return $persisted;
    }
}
