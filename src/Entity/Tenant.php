<?php

namespace App\Entity;

use App\Repository\TenantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TenantRepository::class)]
class Tenant
{
    /**
     * Identifiant unique du tenant.
     * Doctrine va le générer automatiquement.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom commercial du tenant
     * Exemple : "Restaurant Atlas"
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Version "URL-friendly" du nom
     * Exemple : "restaurant-atlas"
     */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    /**
     * Nom de la base PostgreSQL dédiée au tenant.
     * On le met nullable au début car la base est créée
     * après l’instanciation de l’objet Tenant.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dbName = null;

    /**
     * Plan d’abonnement choisi par le tenant
     * Exemple : basic, premium, enterprise
     */
    #[ORM\Column(length: 255)]
    private ?string $plan = null;

    /**
     * Date de création du tenant
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Date de dernière mise à jour
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Retourne le nom du tenant
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Définit le nom du tenant
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Retourne le slug
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * Définit le slug
     */
    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Retourne le nom de la base dédiée
     */
    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    /**
     * Définit le nom de la base dédiée
     */
    public function setDbName(?string $dbName): static
    {
        $this->dbName = $dbName;

        return $this;
    }

    /**
     * Retourne le plan
     */
    public function getPlan(): ?string
    {
        return $this->plan;
    }

    /**
     * Définit le plan
     */
    public function setPlan(string $plan): static
    {
        $this->plan = $plan;

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