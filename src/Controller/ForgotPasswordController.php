<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordResetService $passwordResetService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim((string) ($data['email'] ?? ''));

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Adresse e-mail invalide.'], 422);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Toujours retourner 200 : ne pas révéler si l'email existe.
        if (null === $user) {
            return $this->json(['message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.']);
        }

        $this->passwordResetService->prepareToken($user);
        $this->em->flush();
        $this->passwordResetService->sendResetEmail($user);

        return $this->json(['message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.']);
    }
}
