<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\Quote;
use Doctrine\ORM\EntityManagerInterface;

class QuoteToInvoiceService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Convertit un devis en facture.
     *
     * Étapes :
     * 1. Créer une nouvelle facture
     * 2. Copier les informations principales du devis
     * 3. Copier toutes les lignes du devis vers la facture
     * 4. Initialiser les montants financiers de suivi
     * 5. Mettre à jour le statut du devis
     * 6. Sauvegarder le tout
     */
    public function convert(Quote $quote): Invoice
    {
        if ($quote->getStatus() === 'converted') {
            throw new \RuntimeException('Ce devis a déjà été converti en facture.');
        }

        $invoice = new Invoice();
        $invoice->setTenant($quote->getTenant());
        $invoice->setClient($quote->getClient());
        $invoice->setQuote($quote);

        $invoice->setNumber($this->generateInvoiceNumber());

        // Choix métier :
        // - draft si tu veux une étape future "envoyer la facture"
        // - pending si la facture est active immédiatement
       $invoice->setStatus('pending');

        $invoice->setSubtotal((float) $quote->getSubtotal());
        $invoice->setTaxAmount((float) $quote->getTaxAmount());
        $invoice->setTotal((float) $quote->getTotal());

        // Initialisation des champs financiers
        $invoice->setPaidAmount(0.0);
        $invoice->setRemainingAmount((float) $quote->getTotal());

        $invoice->setDueDate(new \DateTimeImmutable('+30 days'));
        $invoice->setCreatedAt(new \DateTimeImmutable());
        $invoice->setUpdatedAt(new \DateTimeImmutable());

        foreach ($quote->getItems() as $quoteItem) {
            $invoiceItem = new InvoiceItem();
            $invoiceItem->setLabel((string) $quoteItem->getLabel());
            $invoiceItem->setQuantity((float) $quoteItem->getQuantity());
            $invoiceItem->setUnitPrice((float) $quoteItem->getUnitPrice());
            $invoiceItem->setLineTotal((float) $quoteItem->getLineTotal());

            $invoice->addItem($invoiceItem);
        }

        $quote->setStatus('converted');
        $quote->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }

    /**
     * Génère un numéro de facture simple.
     * Plus tard, on pourra faire une vraie séquence par tenant.
     */
    private function generateInvoiceNumber(): string
    {
        return 'FAC-' . date('Ymd-His');
    }
}