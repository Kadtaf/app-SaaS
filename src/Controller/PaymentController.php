<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentRepository;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    /**
     * Crée un paiement pour une facture donnée.
     *
     * POST /api/invoices/{id}/payments
     *
     * Body JSON :
     * {
     *   "amount": 100.50,
     *   "method": "card",
     *   "externalReference": "stripe_pi_123",
     *   "paidAt": "2026-04-18T15:30:00"
     * }
     */
    #[Route('/api/invoices/{id}/payments', name: 'create_payment_for_invoice', methods: ['POST'])]
    public function createPaymentForInvoice(
        int $id,
        Request $request,
        InvoiceRepository $invoiceRepository,
        PaymentService $paymentService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        $invoice = $invoiceRepository->find($id);

        if (!$invoice instanceof Invoice) {
            return new JsonResponse([
                'error' => 'Facture introuvable.'
            ], 404);
        }

        // Sécurité multi-tenant
        if ($invoice->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Cette facture n\'appartient pas à votre tenant.'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Le corps de la requête doit être un JSON valide.'
            ], 400);
        }

        if (!isset($data['amount'])) {
            return new JsonResponse([
                'error' => 'Le champ "amount" est obligatoire.'
            ], 400);
        }

        $amount = (float) $data['amount'];
        $method = !empty($data['method']) ? (string) $data['method'] : 'card';
        $externalReference = !empty($data['externalReference']) ? (string) $data['externalReference'] : null;

        $paidAt = null;
        if (!empty($data['paidAt'])) {
            try {
                $paidAt = new \DateTimeImmutable($data['paidAt']);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'error' => 'Le format de "paidAt" est invalide.'
                ], 400);
            }
        }

        try {
            $payment = $paymentService->addPaymentToInvoice(
                $invoice,
                $amount,
                $method,
                $externalReference,
                $paidAt
            );

            return new JsonResponse([
                'message' => 'Paiement enregistré avec succès.',
                'payment' => [
                    'id' => $payment->getId(),
                    'amount' => $payment->getAmount(),
                    'method' => $payment->getMethod(),
                    'status' => $payment->getStatus(),
                    'externalReference' => $payment->getExternalReference(),
                    'paidAt' => $payment->getPaidAt()->format('Y-m-d H:i:s'),
                    'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                    'invoice' => [
                        'id' => $invoice->getId(),
                        'number' => $invoice->getNumber(),
                        'status' => $invoice->getStatus(),
                        'total' => $invoice->getTotal(),
                        'paidAmount' => $invoice->getPaidAmount(),
                        'remainingAmount' => $invoice->getRemainingAmount(),
                        'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                    ],
                ]
            ], 201);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'enregistrement du paiement : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste tous les paiements d'une facture.
     *
     * GET /api/invoices/{id}/payments
     */
    #[Route('/api/invoices/{id}/payments', name: 'list_payments_for_invoice', methods: ['GET'])]
    public function listPaymentsForInvoice(
        int $id,
        InvoiceRepository $invoiceRepository,
        PaymentRepository $paymentRepository
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        $invoice = $invoiceRepository->find($id);

        if (!$invoice instanceof Invoice) {
            return new JsonResponse([
                'error' => 'Facture introuvable.'
            ], 404);
        }

        // Sécurité multi-tenant
        if ($invoice->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Cette facture n\'appartient pas à votre tenant.'
            ], 403);
        }

        $payments = $paymentRepository->findByInvoiceId($invoice->getId());

        $data = [];
        foreach ($payments as $payment) {
            $data[] = [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'method' => $payment->getMethod(),
                'status' => $payment->getStatus(),
                'externalReference' => $payment->getExternalReference(),
                'paidAt' => $payment->getPaidAt()->format('Y-m-d H:i:s'),
                'createdAt' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data, 200);
    }
}