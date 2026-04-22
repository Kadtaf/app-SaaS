<?php

namespace App\Repository;

use App\Entity\Reminder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reminder::class);
    }

    /**
     * Retourne toutes les relances d'une facture, de la plus récente à la plus ancienne.
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.invoice = :invoiceId')
            ->setParameter('invoiceId', $invoiceId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la dernière relance d'une facture.
     */
    public function findLastByInvoiceId(int $invoiceId): ?Reminder
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.invoice = :invoiceId')
            ->setParameter('invoiceId', $invoiceId)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}