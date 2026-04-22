<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Quote;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Crée une facture complète :
     * - affecte le tenant
     * - affecte le client
     * - génère un numéro
     * - crée les lignes
     * - calcule les montants
     * - initialise les champs financiers
     * - persiste la facture
     */
    public function createInvoice(
        Tenant $tenant,
        Client $client,
        array $itemsData,
        ?string $dueDate = null,
        float $taxRate = 0,
        ?Quote $quote = null
    ): Invoice {
        $invoice = new Invoice();
        $invoice->setTenant($tenant);
        $invoice->setClient($client);
        $invoice->setQuote($quote);
        $invoice->setNumber($this->generateInvoiceNumber());
        $invoice->setStatus('pending');
        $invoice->setCreatedAt(new \DateTimeImmutable());
        $invoice->setUpdatedAt(new \DateTimeImmutable());

        if ($dueDate) {
            $invoice->setDueDate(new \DateTimeImmutable($dueDate));
        }

        $subtotal = 0.0;

        foreach ($itemsData as $itemData) {
            $quantity = isset($itemData['quantity']) ? (float) $itemData['quantity'] : 0.0;
            $unitPrice = isset($itemData['unitPrice']) ? (float) $itemData['unitPrice'] : 0.0;
            $lineTotal = $quantity * $unitPrice;

            $item = new InvoiceItem();
            $item->setLabel($itemData['label'] ?? 'Article');
            $item->setQuantity($quantity);
            $item->setUnitPrice($unitPrice);
            $item->setLineTotal($lineTotal);

            $invoice->addItem($item);

            $subtotal += $lineTotal;
        }

        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $invoice->setSubtotal($subtotal);
        $invoice->setTaxAmount($taxAmount);
        $invoice->setTotal($total);

        // Initialisation des champs financiers agrégés
        $invoice->setPaidAmount(0.0);
        $invoice->setRemainingAmount($total);

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }

    /**
     * Génère un numéro de facture simple.
     * Pour le MVP, on utilise une chaîne basée sur la date/heure.
     * Plus tard, on pourra faire une vraie séquence métier par tenant.
     */
    private function generateInvoiceNumber(): string
    {
        return 'FAC-' . date('Ymd-His');
    }
}