<?php

namespace App\Entity;

use App\Repository\QuoteItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuoteItemRepository::class)]
class QuoteItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quote::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: 'float')]
    private ?float $quantity = 0;

    #[ORM\Column(type: 'float')]
    private ?float $unitPrice = 0;

    #[ORM\Column(type: 'float')]
    private ?float $lineTotal = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): static
    {
        $this->quote = $quote;
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