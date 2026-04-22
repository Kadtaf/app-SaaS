<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Retourne tous les clients d'un tenant, du plus récent au plus ancien
     */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}