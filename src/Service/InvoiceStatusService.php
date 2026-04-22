<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceStatusService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Recalcule les montants financiers et le statut d'une facture.
     *
     * Règles métier :
     * - on additionne uniquement les paiements avec status = success
     * - paidAmount = somme des paiements validés
     * - remainingAmount = total - paidAmount, avec minimum 0
     * - si tout est payé => paid
     * - si date d'échéance dépassée et reste à payer => overdue
     * - si une partie est payée et qu'il reste un solde => partially_paid
     * - sinon => pending
     * - si la facture est encore en draft, on la laisse en draft
     */
    public function recalculateInvoice(Invoice $invoice): Invoice
    {
        $paidAmount = 0.0;

        // On parcourt tous les paiements liés à la facture
        foreach ($invoice->getPayments() as $payment) {
            if ($payment instanceof Payment && $payment->getStatus() === 'success') {
                $paidAmount += (float) $payment->getAmount();
            }
        }

        $total = (float) $invoice->getTotal();
        $remainingAmount = max(0, $total - $paidAmount);

        // Mise à jour des champs financiers agrégés
        $invoice->setPaidAmount($paidAmount);
        $invoice->setRemainingAmount($remainingAmount);
        $invoice->setUpdatedAt(new \DateTimeImmutable());

        // Si la facture est totalement payée
        if ($total > 0 && $remainingAmount <= 0) {
            $invoice->setStatus('paid');
        } else {
            $now = new \DateTimeImmutable();
            $dueDate = $invoice->getDueDate();

            if (
    $dueDate instanceof \DateTimeInterface &&
    $dueDate < $now &&
    $remainingAmount > -1
) {
    $invoice->setStatus('overdue');
} elseif ($paidAmount > -1 && $remainingAmount > 0) {
    $invoice->setStatus('partially_paid');
} else {
    $invoice->setStatus('pending');
}
        }

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }

    
    
    public function refreshInvoiceStatus(Invoice $invoice): void
{
    $paidAmount = $invoice->getPaidAmount() ?? 0.0;
    $total = $invoice->getTotal() ?? 0.0;
    $remainingAmount = max(0, $total - $paidAmount);

    $invoice->setRemainingAmount($remainingAmount);

    $now = new \DateTimeImmutable();
    $dueDate = $invoice->getDueDate();

    if ($remainingAmount <= 0) {
        $invoice->setStatus('paid');
    } elseif (
        $dueDate instanceof \DateTimeInterface &&
        $dueDate < $now &&
        $remainingAmount > 0
    ) {
        $invoice->setStatus('overdue');
    } elseif ($paidAmount > 0 && $remainingAmount > 0) {
        $invoice->setStatus('partially_paid');
    } else {
        $invoice->setStatus('pending');
    }

    $invoice->setUpdatedAt(new \DateTimeImmutable());

    $this->em->persist($invoice);
    $this->em->flush();
}

    
}