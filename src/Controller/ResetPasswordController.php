<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $token = trim((string) ($data['token'] ?? ''));
        $newPassword = (string) ($data['password'] ?? '');

        if ('' === $token) {
            return $this->json(['message' => 'Token manquant.'], 400);
        }

        $user = $this->userRepository->findByPasswordResetToken($token);

        if (null === $user || !$user->isPasswordResetTokenValid()) {
            return $this->json(['message' => 'Token invalide ou expiré.'], 400);
        }

        $violations = $this->validator->validate($newPassword, [
            new Assert\NotBlank(),
            new Assert\Length(min: 8, max: 4096),
            new Assert\Regex(
                pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\w\s]).+$/',
                message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
                match: true,
            ),
            new Assert\NotCompromisedPassword(),
        ]);

        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            return $this->json(['message' => 'Mot de passe invalide.', 'errors' => $errors], 422);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->clearPasswordResetToken();
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
