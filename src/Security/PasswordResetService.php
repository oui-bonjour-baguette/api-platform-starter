<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

#[WithMonologChannel('security')]
class PasswordResetService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(APP_BASE_URL)%')]
        private readonly string $baseUrl,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function prepareToken(User $user): void
    {
        $user->setPasswordResetToken(bin2hex(random_bytes(32)));
        $user->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
    }

    public function sendResetEmail(User $user): void
    {
        $url = $this->baseUrl.'/reset-password?token='.urlencode((string) $user->getPasswordResetToken());

        $email = new TemplatedEmail()
            ->from('no-reply@'.parse_url($this->baseUrl, \PHP_URL_HOST))
            ->to($user->getEmail())
            ->subject('Réinitialisez votre mot de passe')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'url' => $url,
                'user' => $user,
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Password reset email sent.', ['user_id' => $user->getId()]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Password reset email failed.', [
                'user_id' => $user->getId(),
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
