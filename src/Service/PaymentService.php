<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceStatusService $invoiceStatusService
    ) {
    }

    /**
     * Enregistre un paiement sur une facture
     * puis recalcule automatiquement son état financier.
     *
     * Règles MVP :
     * - amount doit être > 0
     * - on refuse un paiement sur une facture déjà soldée
     * - on refuse un paiement supérieur au reste à payer
     * - le paiement est enregistré avec status = success
     * - après enregistrement, on recalcule paidAmount, remainingAmount et status
     */
    public function addPaymentToInvoice(
        Invoice $invoice,
        float $amount,
        string $method = 'card',
        ?string $externalReference = null,
        ?\DateTimeImmutable $paidAt = null
    ): Payment {
        if ($amount <= 0) {
            throw new \RuntimeException('Le montant du paiement doit être supérieur à 0.');
        }

        $total = (float) $invoice->getTotal();
        $paidAmount = (float) $invoice->getPaidAmount();
        $remainingAmount = (float) $invoice->getRemainingAmount();

        // Sécurité : si remainingAmount n'est pas encore bien initialisé
        // sur une ancienne facture, on le recalculera à la volée
        if ($remainingAmount <= 0 && $total > $paidAmount) {
            $remainingAmount = max(0, $total - $paidAmount);
        }

        // Si la facture est déjà totalement payée
        if ($total > 0 && $paidAmount >= $total) {
            throw new \RuntimeException('Cette facture est déjà totalement payée.');
        }

        // Protection contre le surpaiement
        if ($remainingAmount > 0 && $amount > $remainingAmount) {
            throw new \RuntimeException(
                'Le montant du paiement dépasse le reste à payer de la facture.'
            );
        }

        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setAmount($amount);
        $payment->setMethod($method);
        $payment->setStatus('success');
        $payment->setExternalReference($externalReference);

        if ($paidAt instanceof \DateTimeImmutable) {
            $payment->setPaidAt($paidAt);
        } else {
            $payment->setPaidAt(new \DateTimeImmutable());
        }

        $payment->setCreatedAt(new \DateTimeImmutable());
        $payment->setUpdatedAt(new \DateTimeImmutable());

        // Important :
        // on garde la relation synchronisée côté objet
        // pour que la facture "voie" immédiatement le nouveau paiement
        $invoice->addPayment($payment);
        $invoice->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->persist($invoice);
        $this->em->flush();

        // Recalcul automatique après création du paiement
        $this->invoiceStatusService->recalculateInvoice($invoice);

        return $payment;
    }
}