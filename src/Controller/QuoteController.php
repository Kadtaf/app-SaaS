<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\QuoteItem;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use App\Service\QuoteToInvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class QuoteController extends AbstractController
{
    /**
     * Liste tous les devis du tenant de l'utilisateur connecté.
     *
     * Route :
     * GET /api/quotes
     *
     * Fonctionnement :
     * - récupère l'utilisateur connecté via JWT
     * - récupère le tenant de cet utilisateur
     * - charge tous les devis du tenant
     * - retourne un tableau JSON de devis + lignes
     */
    #[Route('/api/quotes', name: 'list_quotes', methods: ['GET'])]
    public function listQuotes(QuoteRepository $quoteRepository): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        // Sécurité : on refuse l'accès si l'utilisateur n'est pas authentifié
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();
        $quotes = $quoteRepository->findByTenant($tenant);

        $data = [];

        foreach ($quotes as $quote) {
            $items = [];

            // On construit un tableau des lignes du devis
            foreach ($quote->getItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'label' => $item->getLabel(),
                    'quantity' => $item->getQuantity(),
                    'unitPrice' => $item->getUnitPrice(),
                    'lineTotal' => $item->getLineTotal(),
                ];
            }

            $data[] = [
                'id' => $quote->getId(),
                'number' => $quote->getNumber(),
                'status' => $quote->getStatus(),
                'subtotal' => $quote->getSubtotal(),
                'taxAmount' => $quote->getTaxAmount(),
                'total' => $quote->getTotal(),
                'expiresAt' => $quote->getExpiresAt()?->format('Y-m-d'),
                'createdAt' => $quote->getCreatedAt()?->format('Y-m-d H:i:s'),
                'client' => [
                    'id' => $quote->getClient()?->getId(),
                    'name' => $quote->getClient()?->getName(),
                    'email' => $quote->getClient()?->getEmail(),
                ],
                'items' => $items,
            ];
        }

        return new JsonResponse($data, 200);
    }

    /**
     * Crée un devis pour le tenant de l'utilisateur connecté.
     *
     * Route :
     * POST /api/quotes
     *
     * JSON attendu :
     * {
     *   "clientId": 1,
     *   "taxRate": 20,
     *   "expiresAt": "2026-05-01",
     *   "items": [
     *     { "label": "Buffet", "quantity": 1, "unitPrice": 450 },
     *     { "label": "Boissons", "quantity": 1, "unitPrice": 120 }
     *   ]
     * }
     */
    #[Route('/api/quotes', name: 'create_quote', methods: ['POST'])]
    public function createQuote(
        Request $request,
        ClientRepository $clientRepository,
        EntityManagerInterface $em
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

        // Vérifie que le body est un JSON valide
        if (!is_array($data)) {
            return new JsonResponse([
                'error' => 'Le corps de la requête doit être un JSON valide.'
            ], 400);
        }

        // Vérifie que le client est fourni
        if (empty($data['clientId'])) {
            return new JsonResponse([
                'error' => 'Le champ "clientId" est obligatoire.'
            ], 400);
        }

        // Vérifie que des lignes sont fournies
        if (empty($data['items']) || !is_array($data['items'])) {
            return new JsonResponse([
                'error' => 'Le champ "items" est obligatoire et doit être un tableau.'
            ], 400);
        }

        // Récupère le client
        $client = $clientRepository->find($data['clientId']);

        if (!$client instanceof Client) {
            return new JsonResponse([
                'error' => 'Client introuvable.'
            ], 404);
        }

        // Sécurité multi-tenant : le client doit appartenir au tenant du user
        if ($client->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Ce client n\'appartient pas à votre tenant.'
            ], 403);
        }

        // Création du devis
        $quote = new Quote();
        $quote->setTenant($tenant);
        $quote->setClient($client);
        $quote->setNumber('DEV-' . date('Ymd-His')); // numéro simple pour le MVP
        $quote->setStatus('draft');
        $quote->setCreatedAt(new \DateTimeImmutable());
        $quote->setUpdatedAt(new \DateTimeImmutable());

        // Date d'expiration optionnelle
        if (!empty($data['expiresAt'])) {
            $quote->setExpiresAt(new \DateTimeImmutable($data['expiresAt']));
        }

        $subtotal = 0.0;
        $taxRate = isset($data['taxRate']) ? (float) $data['taxRate'] : 0.0;

        // Création des lignes de devis
        foreach ($data['items'] as $itemData) {
            if (empty($itemData['label'])) {
                return new JsonResponse([
                    'error' => 'Chaque ligne doit contenir un champ "label".'
                ], 400);
            }

            $quantity = isset($itemData['quantity']) ? (float) $itemData['quantity'] : 0.0;
            $unitPrice = isset($itemData['unitPrice']) ? (float) $itemData['unitPrice'] : 0.0;
            $lineTotal = $quantity * $unitPrice;

            $item = new QuoteItem();
            $item->setLabel($itemData['label']);
            $item->setQuantity($quantity);
            $item->setUnitPrice($unitPrice);
            $item->setLineTotal($lineTotal);

            $quote->addItem($item);

            $subtotal += $lineTotal;
        }

        // Calcul des totaux
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $quote->setSubtotal($subtotal);
        $quote->setTaxAmount($taxAmount);
        $quote->setTotal($total);

        // Sauvegarde en base
        $em->persist($quote);
        $em->flush();

        // Reconstruction des lignes pour la réponse JSON
        $items = [];
        foreach ($quote->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'label' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'lineTotal' => $item->getLineTotal(),
            ];
        }

        return new JsonResponse([
            'message' => 'Devis créé avec succès.',
            'quote' => [
                'id' => $quote->getId(),
                'number' => $quote->getNumber(),
                'status' => $quote->getStatus(),
                'subtotal' => $quote->getSubtotal(),
                'taxAmount' => $quote->getTaxAmount(),
                'total' => $quote->getTotal(),
                'expiresAt' => $quote->getExpiresAt()?->format('Y-m-d'),
                'client' => [
                    'id' => $client->getId(),
                    'name' => $client->getName(),
                ],
                'items' => $items,
                'createdAt' => $quote->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * Convertit un devis en facture.
     *
     * Route :
     * POST /api/quotes/{id}/convert-to-invoice
     *
     * Fonctionnement :
     * - vérifie l'utilisateur connecté
     * - vérifie que le devis existe
     * - vérifie que le devis appartient au bon tenant
     * - délègue la conversion au service métier
     */
    #[Route('/api/quotes/{id}/convert-to-invoice', name: 'convert_quote_to_invoice', methods: ['POST'])]
    public function convertToInvoice(
        int $id,
        QuoteRepository $quoteRepository,
        QuoteToInvoiceService $quoteToInvoiceService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $tenant = $user->getTenant();

        // On récupère le devis demandé
        $quote = $quoteRepository->find($id);

        if (!$quote instanceof Quote) {
            return new JsonResponse([
                'error' => 'Devis introuvable.'
            ], 404);
        }

        // Sécurité multi-tenant :
        // on vérifie que le devis appartient bien au tenant du user connecté
        if ($quote->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Ce devis n\'appartient pas à votre tenant.'
            ], 403);
        }

        try {
            // Délégation au service métier pour faire la conversion
            $invoice = $quoteToInvoiceService->convert($quote);

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
                'message' => 'Devis converti en facture avec succès.',
                'invoice' => [
                    'id' => $invoice->getId(),
                    'number' => $invoice->getNumber(),
                    'status' => $invoice->getStatus(),
                    'subtotal' => $invoice->getSubtotal(),
                    'taxAmount' => $invoice->getTaxAmount(),
                    'total' => $invoice->getTotal(),
                    'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                    'client' => [
                        'id' => $invoice->getClient()?->getId(),
                        'name' => $invoice->getClient()?->getName(),
                    ],
                    'quote' => [
                        'id' => $quote->getId(),
                        'number' => $quote->getNumber(),
                        'status' => $quote->getStatus(),
                    ],
                    'items' => $items,
                    'createdAt' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (\RuntimeException $e) {
            // Erreurs métier (ex : devis déjà converti)
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            // Erreurs techniques
            return new JsonResponse([
                'error' => 'Erreur lors de la conversion du devis en facture : ' . $e->getMessage()
            ], 500);
        }
    }
}
