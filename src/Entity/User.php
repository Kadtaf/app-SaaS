<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Cet email existe déjà.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * Identifiant unique de l'utilisateur
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Chaque utilisateur appartient à un tenant.
     * Exemple :
     * - un restaurateur = tenant
     * - ses employés = users liés à ce tenant
     */
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    /**
     * Email utilisé comme identifiant de connexion.
     * On le met en unique pour éviter les doublons.
     */
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * Mot de passe hashé.
     * On ne stocke jamais un mot de passe en clair.
     */
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    /**
     * Tableau des rôles Symfony.
     * Exemple :
     * ['ROLE_ADMIN']
     * ['ROLE_MANAGER']
     * ['ROLE_EMPLOYEE']
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Nom complet de l'utilisateur
     * utile pour l'interface d'administration.
     */
    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    /**
     * Permet de désactiver un utilisateur
     * sans le supprimer de la base.
     */
    #[ORM\Column]
    private bool $isActive = true;

    /**
     * Date de création
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Date de mise à jour
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Retourne le tenant auquel appartient l'utilisateur
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Définit le tenant auquel appartient l'utilisateur
     */
    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Retourne l'email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Définit l'email
     */
    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    /**
     * Méthode requise par Symfony Security.
     * Elle retourne l'identifiant public de l'utilisateur.
     * Ici, on choisit l'email.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Retourne les rôles de l'utilisateur.
     * On garantit toujours au minimum ROLE_USER.
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * Définit les rôles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Retourne le mot de passe hashé
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    /**
     * Définit le mot de passe hashé
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Méthode requise par Symfony Security.
     * Aujourd'hui, elle est souvent vide,
     * sauf si on stocke temporairement des données sensibles.
     */
    public function eraseCredentials(): void
    {
        // Rien à faire ici pour le moment
    }

    /**
     * Retourne le nom complet
     */
    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    /**
     * Définit le nom complet
     */
    public function setFullName(string $fullName): static
    {
        $this->fullName = trim($fullName);

        return $this;
    }

    /**
     * Indique si l'utilisateur est actif
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Active ou désactive l'utilisateur
     */
    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Retourne la date de création
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Définit la date de création
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Retourne la date de mise à jour
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Définit la date de mise à jour
     */
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
