<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Retourne tous les paiements d'une facture.
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.invoice = :invoiceId')
            ->setParameter('invoiceId', $invoiceId)
            ->orderBy('p.paidAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}