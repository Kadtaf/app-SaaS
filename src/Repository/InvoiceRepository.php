<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository dédié à la lecture des factures.
 * On y place les requêtes personnalisées liées à l'entité Invoice.
 *
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Retourne toutes les factures d'un tenant,
     * du plus récent au plus ancien.
     */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.client', 'c')
            ->addSelect('c')
            ->andWhere('i.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}