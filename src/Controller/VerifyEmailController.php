<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyEmailController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->query->get('token', '');

        if ('' === $token) {
            return $this->json(['message' => 'Token manquant.'], 400);
        }

        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);

        if (null === $user) {
            return $this->json(['message' => 'Token invalide ou expiré.'], 404);
        }

        if ($user->isEmailVerified()) {
            return $this->json(['message' => 'Adresse déjà vérifiée.']);
        }

        $user->setEmailVerified(true)->setEmailVerificationToken(null);
        $this->em->flush();

        return $this->json(['message' => 'Adresse e-mail vérifiée avec succès.']);
    }
}
