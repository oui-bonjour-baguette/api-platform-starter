<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use App\State\User\MeProvider;
use App\State\User\UserPasswordHasherProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ApiResource(
    shortName: 'User',
    operations: [
        new Post(
            uriTemplate: '/users',
            normalizationContext: ['groups' => ['user:read']],
            denormalizationContext: ['groups' => ['user:create']],
            security: "is_granted('PUBLIC_ACCESS')",
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: UserPasswordHasherProcessor::class,
        ),
        new Get(
            uriTemplate: '/users/{id}',
            normalizationContext: ['groups' => ['user:read']],
            security: "is_granted('ROLE_ADMIN') or object == user",
        ),
        new Get(
            uriTemplate: '/me',
            uriVariables: [],
            normalizationContext: ['groups' => ['user:read']],
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: true,
            // Pas d'{id} dans l'URI : provider retourne l'utilisateur courant.
            provider: MeProvider::class,
        ),
    ],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // UUID v7 pré-assigné : triable par date, non énumérable,
    // pas besoin d'un générateur Doctrine custom.
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['user:read'])]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:create'])]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    /** Hash bcrypt/argon — jamais sérialisé. */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $isEmailVerified = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    /** Mot de passe en clair, uniquement à l'entrée. */
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 8, max: 4096, groups: ['user:create'])]
    #[Assert\Regex(
        pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\w\s]).+$/',
        message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
        match: true,
        groups: ['user:create']
    )]
    #[Assert\NotCompromisedPassword(groups: ['user:create'])]
    #[Groups(['user:create'])]
    private ?string $plainPassword = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setEmailVerified(bool $verified): self
    {
        $this->isEmailVerified = $verified;

        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $token): self
    {
        $this->emailVerificationToken = $token;

        return $this;
    }
}
