<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailVerificationService
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(APP_BASE_URL)%')]
        private string $baseUrl,
    ) {
    }

    public function prepareToken(User $user): void
    {
        $user->setEmailVerificationToken(bin2hex(random_bytes(32)));
        $user->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
    }

    public function sendVerificationEmail(User $user): void
    {
        $url = $this->baseUrl . '/api/verify-email?token=' . urlencode((string) $user->getEmailVerificationToken());

        $email = (new Email())
            ->from('no-reply@' . parse_url($this->baseUrl, PHP_URL_HOST))
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse e-mail')
            ->html(<<<HTML
                <p>Bonjour,</p>
                <p>Merci de vous être inscrit. Veuillez confirmer votre adresse e-mail en cliquant sur le lien ci-dessous :</p>
                <p><a href="{$url}">{$url}</a></p>
                <p>Si vous n'avez pas créé de compte, ignorez cet e-mail.</p>
                HTML)
            ->text("Confirmez votre adresse e-mail en visitant : {$url}");

        $this->mailer->send($email);
    }
}
