<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\ReminderService;
use App\Service\InvoiceStatusService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-overdue-reminders',
    description: 'Envoie automatiquement les relances pour les factures overdue.'
)]
class SendOverdueRemindersCommand extends Command
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private ReminderService $reminderService,
        private InvoiceStatusService $invoiceStatusService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Envoi automatique des relances overdue');

        $invoices = $this->invoiceRepository->findAll();

        $processed = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            if (!$invoice instanceof Invoice) {
                continue;
            }

            try {
                // Recalcul du statut avant décision
                $this->invoiceStatusService->refreshInvoiceStatus($invoice);

                if ($invoice->getStatus() !== 'overdue') {
                    $skipped++;
                    continue;
                }

                if (($invoice->getRemainingAmount() ?? 0) <= 0) {
                    $skipped++;
                    continue;
                }

                $processed++;

                $reminder = $this->reminderService->createAndSendReminderForInvoice($invoice, 'email');

                $io->success(sprintf(
                    'Relance envoyée pour la facture #%s (Reminder ID: %d, niveau %d).',
                    $invoice->getNumber(),
                    $reminder->getId(),
                    $reminder->getLevel()
                ));

                $sent++;
            } catch (\Throwable $e) {
                $failed++;

                $io->error(sprintf(
                    'Échec pour la facture #%s : %s',
                    $invoice->getNumber(),
                    $e->getMessage()
                ));
            }
        }

        $io->section('Résumé');
        $io->listing([
            'Factures overdue traitées : ' . $processed,
            'Relances envoyées : ' . $sent,
            'Factures ignorées : ' . $skipped,
            'Erreurs : ' . $failed,
        ]);

        return Command::SUCCESS;
    }
}