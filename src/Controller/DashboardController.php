<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    /**
     * Dashboard principal :
     * KPI globaux du tenant connecté.
     *
     * GET /api/dashboard/overview
     */
    #[Route('/api/dashboard/overview', name: 'dashboard_overview', methods: ['GET'])]
    public function overview(DashboardService $dashboardService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        return new JsonResponse(
            $dashboardService->getOverview($tenant),
            200
        );
    }

    /**
     * Tendance mensuelle :
     * montant facturé et encaissé par mois.
     *
     * GET /api/dashboard/revenue
     */
    #[Route('/api/dashboard/revenue', name: 'dashboard_revenue', methods: ['GET'])]
    public function revenue(DashboardService $dashboardService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        return new JsonResponse(
            $dashboardService->getRevenueByMonth($tenant),
            200
        );
    }

    /**
     * Répartition des factures par statut.
     *
     * GET /api/dashboard/invoices-status
     */
    #[Route('/api/dashboard/invoices-status', name: 'dashboard_invoices_status', methods: ['GET'])]
    public function invoicesStatus(DashboardService $dashboardService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        return new JsonResponse(
            $dashboardService->getInvoicesStatusDistribution($tenant),
            200
        );
    }
}