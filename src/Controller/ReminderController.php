<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Service\ReminderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ReminderController extends AbstractController
{
    /**
     * Génère une relance pour une facture overdue.
     *
     * POST /api/invoices/{id}/reminders
     *
     * Body optionnel :
     * {
     *   "channel": "email"
     * }
     */
    #[Route('/api/invoices/{id}/reminders', name: 'create_invoice_reminder', methods: ['POST'])]
    public function createReminder(
        int $id,
        Request $request,
        InvoiceRepository $invoiceRepository,
        ReminderService $reminderService
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

        if ($invoice->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Cette facture n\'appartient pas à votre tenant.'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);
        $channel = is_array($data) && !empty($data['channel']) ? (string) $data['channel'] : 'email';

        try {
            $reminder = $reminderService->createReminderForInvoice($invoice, $channel);

            return new JsonResponse([
                'message' => 'Relance générée avec succès.',
                'reminder' => [
                    'id' => $reminder->getId(),
                    'level' => $reminder->getLevel(),
                    'channel' => $reminder->getChannel(),
                    'subject' => $reminder->getSubject(),
                    'message' => $reminder->getMessage(),
                    'status' => $reminder->getStatus(),
                    'createdAt' => $reminder->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
                'invoice' => [
                    'id' => $invoice->getId(),
                    'number' => $invoice->getNumber(),
                    'status' => $invoice->getStatus(),
                    'remainingAmount' => $invoice->getRemainingAmount(),
                    'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                ]
            ], 201);

        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la génération de la relance : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste les relances d'une facture.
     *
     * GET /api/invoices/{id}/reminders
     */
    #[Route('/api/invoices/{id}/reminders', name: 'list_invoice_reminders', methods: ['GET'])]
    public function listReminders(
        int $id,
        InvoiceRepository $invoiceRepository,
        ReminderService $reminderService
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

        if ($invoice->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Cette facture n\'appartient pas à votre tenant.'
            ], 403);
        }

        $reminders = $reminderService->listRemindersForInvoice($invoice);

        $data = [];
        foreach ($reminders as $reminder) {
            $data[] = [
                'id' => $reminder->getId(),
                'level' => $reminder->getLevel(),
                'channel' => $reminder->getChannel(),
                'subject' => $reminder->getSubject(),
                'message' => $reminder->getMessage(),
                'status' => $reminder->getStatus(),
                'createdAt' => $reminder->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data, 200);
    }

     /**
     * Génère et envoie réellement une relance email pour une facture overdue.
     *
     * POST /api/invoices/{id}/reminders/send
     *
     * Body optionnel :
     * {
     *   "channel": "email"
     * }
     */
    #[Route('/api/invoices/{id}/reminders/send', name: 'send_invoice_reminder', methods: ['POST'])]
    public function sendReminder(
        int $id,
        Request $request,
        InvoiceRepository $invoiceRepository,
        ReminderService $reminderService
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

        if ($invoice->getTenant()?->getId() !== $tenant?->getId()) {
            return new JsonResponse([
                'error' => 'Cette facture n\'appartient pas à votre tenant.'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);
        $channel = is_array($data) && !empty($data['channel'])
            ? (string) $data['channel']
            : 'email';

        try {
            $reminder = $reminderService->createAndSendReminderForInvoice($invoice, $channel);

            return new JsonResponse([
                'message' => 'Relance générée et envoyée avec succès.',
                'reminder' => [
                    'id' => $reminder->getId(),
                    'level' => $reminder->getLevel(),
                    'channel' => $reminder->getChannel(),
                    'subject' => $reminder->getSubject(),
                    'message' => $reminder->getMessage(),
                    'status' => $reminder->getStatus(),
                    'createdAt' => $reminder->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updatedAt' => $reminder->getUpdatedAt()->format('Y-m-d H:i:s'),
                ],
                'invoice' => [
                    'id' => $invoice->getId(),
                    'number' => $invoice->getNumber(),
                    'status' => $invoice->getStatus(),
                    'remainingAmount' => $invoice->getRemainingAmount(),
                    'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                ]
            ], 201);

        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'envoi de la relance : ' . $e->getMessage()
            ], 500);
        }
    }
}