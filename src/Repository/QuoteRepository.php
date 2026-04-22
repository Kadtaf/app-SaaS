<?php

namespace App\Repository;

use App\Entity\Quote;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /**
     * Retourne tous les devis d'un tenant
     * du plus récent au plus ancien.
     */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.client', 'c')
            ->addSelect('c')
            ->andWhere('q.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('q.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}