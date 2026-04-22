<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceController extends AbstractController
{
    /**
     * Retourne toutes les factures du tenant
     * de l'utilisateur connecté.
     *
     * Route :
     * GET /api/invoices
     */
    #[Route('/api/invoices', name: 'list_invoices', methods: ['GET'])]
    public function listInvoices(InvoiceRepository $invoiceRepository): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();
        $invoices = $invoiceRepository->findByTenant($tenant);

        $data = [];

        foreach ($invoices as $invoice) {
            $items = [];

            foreach ($invoice->getItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'label' => $item->getLabel(),
                    'quantity' => $item->getQuantity(),
                    'unitPrice' => $item->getUnitPrice(),
                    'lineTotal' => $item->getLineTotal(),
                ];
            }

            $data[] = [
                'id' => $invoice->getId(),
                'number' => $invoice->getNumber(),
                'status' => $invoice->getStatus(),
                'subtotal' => $invoice->getSubtotal(),
                'taxAmount' => $invoice->getTaxAmount(),
                'total' => $invoice->getTotal(),
                'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                'createdAt' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
                'client' => [
                    'id' => $invoice->getClient()?->getId(),
                    'name' => $invoice->getClient()?->getName(),
                    'email' => $invoice->getClient()?->getEmail(),
                ],
                'items' => $items,
            ];
        }

        return new JsonResponse($data, 200);
    }

    /**
     * Crée une nouvelle facture pour le tenant
     * de l'utilisateur connecté.
     *
     * Route :
     * POST /api/invoices
     */
    #[Route('/api/invoices', name: 'create_invoice', methods: ['POST'])]
    public function createInvoice(
        Request $request,
        ClientRepository $clientRepository,
        InvoiceService $invoiceService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();
        $data = json_decode($request->getContent(), true);

        /**
         * Vérifie que le corps envoyé est bien un JSON valide
         */
        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Le corps de la requête doit être un JSON valide.'
            ], 400);
        }

        /**
         * Vérifie que le client est fourni
         */
        if (empty($data['clientId'])) {
            return new JsonResponse([
                'error' => 'Le champ "clientId" est obligatoire.'
            ], 400);
        }

        /**
         * Vérifie que les lignes sont fournies
         */
        if (empty($data['items']) || !is_array($data['items'])) {
            return new JsonResponse([
                'error' => 'Le champ "items" est obligatoire et doit être un tableau.'
            ], 400);
        }

        /**
         * Récupère le client
         */
        $client = $clientRepository->find($data['clientId']);

        if (!$client instanceof Client) {
            return new JsonResponse([
                'error' => 'Client introuvable.'
            ], 404);
        }

        /**
         * Sécurité multi-tenant :
         * on interdit l'utilisation d'un client appartenant à un autre tenant.
         */
        if ($client->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Ce client n\'appartient pas à votre tenant.'
            ], 403);
        }

        /**
         * Validation simple des lignes
         */
        foreach ($data['items'] as $item) {
            if (empty($item['label'])) {
                return new JsonResponse([
                    'error' => 'Chaque ligne doit contenir un champ "label".'
                ], 400);
            }
        }

        /**
         * Délégation au service métier
         */
        $invoice = $invoiceService->createInvoice(
            tenant: $tenant,
            client: $client,
            itemsData: $data['items'],
            dueDate: $data['dueDate'] ?? null,
            taxRate: isset($data['taxRate']) ? (float) $data['taxRate'] : 0.0
        );

        $items = [];
        foreach ($invoice->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'label' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'lineTotal' => $item->getLineTotal(),
            ];
        }

        return new JsonResponse([
            'message' => 'Facture créée avec succès.',
            'invoice' => [
                'id' => $invoice->getId(),
                'number' => $invoice->getNumber(),
                'status' => $invoice->getStatus(),
                'subtotal' => $invoice->getSubtotal(),
                'taxAmount' => $invoice->getTaxAmount(),
                'total' => $invoice->getTotal(),
                'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                'client' => [
                    'id' => $client->getId(),
                    'name' => $client->getName(),
                ],
                'items' => $items,
                'createdAt' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }
}