<?php

namespace App\Entity;

use App\Repository\InvoiceItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceItemRepository::class)]
class InvoiceItem
{
    /**
     * Identifiant unique de la ligne
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * La facture à laquelle appartient cette ligne
     */
    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    /**
     * Désignation de la ligne
     * Exemple : Menu buffet, livraison, boissons...
     */
    #[ORM\Column(length: 255)]
    private ?string $label = null;

    /**
     * Quantité facturée
     */
    #[ORM\Column(type: 'float')]
    private ?float $quantity = 0;

    /**
     * Prix unitaire HT
     */
    #[ORM\Column(type: 'float')]
    private ?float $unitPrice = 0;

    /**
     * Total de la ligne = quantité x prix unitaire
     */
    #[ORM\Column(type: 'float')]
    private ?float $lineTotal = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);
        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getLineTotal(): ?float
    {
        return $this->lineTotal;
    }

    public function setLineTotal(float $lineTotal): static
    {
        $this->lineTotal = $lineTotal;
        return $this;
    }
}