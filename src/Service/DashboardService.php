<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Retourne les KPI globaux du dashboard pour un tenant.
     *
     * KPI :
     * - nombre total de factures
     * - montant total facturé
     * - montant total encaissé
     * - montant total restant
     * - nombre de factures par statut
     */
    public function getOverview(Tenant $tenant): array
    {
        // Requête sur les factures du tenant pour récupérer les agrégats principaux
        $qb = $this->em->createQueryBuilder();

        $result = $qb
            ->select('COUNT(i.id) AS totalInvoices')
            ->addSelect('COALESCE(SUM(i.total), 0) AS totalBilled')
            ->addSelect('COALESCE(SUM(i.paidAmount), 0) AS totalPaid')
            ->addSelect('COALESCE(SUM(i.remainingAmount), 0) AS totalRemaining')
            ->addSelect("SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) AS pendingCount")
            ->addSelect("SUM(CASE WHEN i.status = 'partially_paid' THEN 1 ELSE 0 END) AS partiallyPaidCount")
            ->addSelect("SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) AS paidCount")
            ->addSelect("SUM(CASE WHEN i.status = 'overdue' THEN 1 ELSE 0 END) AS overdueCount")
            ->from(Invoice::class, 'i')
            ->where('i.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalInvoices' => (int) $result['totalInvoices'],
            'totalBilled' => (float) $result['totalBilled'],
            'totalPaid' => (float) $result['totalPaid'],
            'totalRemaining' => (float) $result['totalRemaining'],
            'pendingCount' => (int) $result['pendingCount'],
            'partiallyPaidCount' => (int) $result['partiallyPaidCount'],
            'paidCount' => (int) $result['paidCount'],
            'overdueCount' => (int) $result['overdueCount'],
        ];
    }

    /**
     * Retourne une tendance mensuelle simple :
     * - montant facturé par mois
     * - montant payé par mois
     *
     * Pour le MVP :
     * - billed = somme des factures créées par mois
     * - paid = somme des paiements effectués par mois
     */
    public function getRevenueByMonth(Tenant $tenant): array
    {
        // 1. Facturation mensuelle à partir des factures
        $invoiceConn = $this->em->getConnection();

        $billedSql = "
            SELECT
                TO_CHAR(created_at, 'YYYY-MM') AS month,
                COALESCE(SUM(total), 0) AS billed
            FROM invoice
            WHERE tenant_id = :tenantId
            GROUP BY TO_CHAR(created_at, 'YYYY-MM')
            ORDER BY month ASC
        ";

        $billedRows = $invoiceConn->executeQuery($billedSql, [
            'tenantId' => $tenant->getId(),
        ])->fetchAllAssociative();

        // 2. Paiements mensuels à partir des paiements liés aux factures du tenant
        $paidSql = "
            SELECT
                TO_CHAR(p.paid_at, 'YYYY-MM') AS month,
                COALESCE(SUM(p.amount), 0) AS paid
            FROM payment p
            INNER JOIN invoice i ON p.invoice_id = i.id
            WHERE i.tenant_id = :tenantId
              AND p.status = 'success'
            GROUP BY TO_CHAR(p.paid_at, 'YYYY-MM')
            ORDER BY month ASC
        ";

        $paidRows = $invoiceConn->executeQuery($paidSql, [
            'tenantId' => $tenant->getId(),
        ])->fetchAllAssociative();

        // 3. Fusion des deux jeux de données par mois
        $months = [];

        foreach ($billedRows as $row) {
            $month = $row['month'];

            if (!isset($months[$month])) {
                $months[$month] = [
                    'month' => $month,
                    'billed' => 0.0,
                    'paid' => 0.0,
                ];
            }

            $months[$month]['billed'] = (float) $row['billed'];
        }

        foreach ($paidRows as $row) {
            $month = $row['month'];

            if (!isset($months[$month])) {
                $months[$month] = [
                    'month' => $month,
                    'billed' => 0.0,
                    'paid' => 0.0,
                ];
            }

            $months[$month]['paid'] = (float) $row['paid'];
        }

        ksort($months);

        return array_values($months);
    }

    /**
     * Retourne la répartition des factures par statut.
     */
    public function getInvoicesStatusDistribution(Tenant $tenant): array
    {
        $qb = $this->em->createQueryBuilder();

        $rows = $qb
            ->select('i.status AS status')
            ->addSelect('COUNT(i.id) AS count')
            ->from(Invoice::class, 'i')
            ->where('i.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->groupBy('i.status')
            ->orderBy('i.status', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            return [
                'status' => $row['status'],
                'count' => (int) $row['count'],
            ];
        }, $rows);
    }
}