<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Payment;
use App\Entity\Reminder;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    /**
     * Identifiant unique de la facture
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Le tenant propriétaire de la facture.
     * Cela permet de garantir l'isolation des données
     * dans le SaaS multi-tenant.
     */
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    /**
     * Le client facturé.
     */
    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    /**
     * Optionnel : lien vers le devis d'origine.
     * Très utile plus tard pour convertir un devis en facture.
     */
    #[ORM\ManyToOne(targetEntity: Quote::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Quote $quote = null;
    

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Reminder::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $reminders;

    /**
     * Numéro métier de la facture.
     * Exemple : FAC-20260417-201501
     */
    #[ORM\Column(length: 50)]
    private ?string $number = null;

    /**
     * Statut métier de la facture.
     * Exemples : draft, sent, paid, overdue, cancelled
     */
    #[ORM\Column(length: 50)]
    private ?string $status = 'draft';

    /**
     * Total hors taxes
     */
    #[ORM\Column(type: 'float')]
    private ?float $subtotal = 0;

    /**
     * Montant total de la TVA
     */
    #[ORM\Column(type: 'float')]
    private ?float $taxAmount = 0;

    /**
     * Total TTC
     */
    #[ORM\Column(type: 'float')]
    private ?float $total = 0;


    /* 
    * Montant déjà payé sur la facture.
     * Permet de calculer le montant restant à payer.
     * Utile pour gérer les paiements partiels.
     * Par exemple, si la facture est de 100€ et que le client a déjà payé 30€, le montant restant sera de 70€.
     * Cela permet aussi de gérer les situations où le client paie en plusieurs fois.
     * Par exemple, un client peut payer
    */
    #[ORM\Column(type: 'float')]
    private float $paidAmount = 0.0;

    /*
     * Montant restant à payer sur la facture.
     * Calculé comme total - paidAmount.
     * Utile pour afficher clairement au client combien il doit encore payer.
     * Par exemple, si la facture est de 100€ et que le client a déjà payé 30€, le montant restant sera de 70€.
     * Cela permet aussi de gérer les situations où le client paie en plusieurs fois.
     * Par exemple, un client peut payer 30€ aujourd'hui, puis 40€ la semaine prochaine, et enfin les 30€ restants à la fin du mois.
     */
    #[ORM\Column(type: 'float')]
    private float $remainingAmount = 0.0;

    /**
     * Date d'échéance de paiement
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    /**
     * Date de création
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Date de dernière mise à jour
     */
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Liste des lignes de facture.
     * Une facture possède plusieurs lignes.
     */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Payment::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $payments;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->paidAmount = 0.0;
        $this->remainingAmount = 0.0;
        $this->reminders = new ArrayCollection(); 
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSubtotal(): ?float
    {
        return $this->subtotal;
    }

    public function setSubtotal(float $subtotal): static
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getTaxAmount(): ?float
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(float $taxAmount): static
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Retourne toutes les lignes de facture
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Ajoute une ligne à la facture
     * et synchronise la relation inverse.
     */
    public function addItem(InvoiceItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInvoice($this);
        }

        return $this;
    }

    /**
     * Supprime une ligne de la facture
     */
    public function removeItem(InvoiceItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getInvoice() === $this) {
                $item->setInvoice(null);
            }
        }

        return $this;
    }

    public function getPaidAmount(): float
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(float $paidAmount): self
    {
        $this->paidAmount = $paidAmount;

        return $this;
    }

    public function getRemainingAmount(): float
    {
        return $this->remainingAmount;
    }

    public function setRemainingAmount(float $remainingAmount): self
    {
        $this->remainingAmount = $remainingAmount;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setInvoice($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getInvoice() === $this) {
                $payment->setInvoice(null);
            }
        }

        return $this;
    }

        /**
     * @return Collection<int, Reminder>
     */
    public function getReminders(): Collection
    {
        return $this->reminders;
    }

    public function addReminder(Reminder $reminder): self
    {
        if (!$this->reminders->contains($reminder)) {
            $this->reminders->add($reminder);
            $reminder->setInvoice($this);
        }

        return $this;
    }

    public function removeReminder(Reminder $reminder): self
    {
        if ($this->reminders->removeElement($reminder)) {
            if ($reminder->getInvoice() === $this) {
                $reminder->setInvoice(null);
            }
        }

        return $this;
    }
}