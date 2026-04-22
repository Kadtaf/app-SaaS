<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Reminder;
use App\Repository\ReminderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ReminderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReminderRepository $reminderRepository,
        private ReminderMailerService $reminderMailerService,
        private InvoiceStatusService $invoiceStatusService
    ) {
    }

    /**
     * Génère une relance pour une facture overdue, sans l'envoyer.
     *
     * Règles :
     * - seules les factures overdue peuvent être relancées
     * - le niveau de relance monte progressivement : 1 -> 2 -> 3 max
     * - la relance est persistée en base avec status = generated
     */
    public function createReminderForInvoice(Invoice $invoice, string $channel = 'email'): Reminder
    {
          $this->invoiceStatusService->refreshInvoiceStatus($invoice);

    if ($invoice->getStatus() !== 'overdue') {
        throw new \RuntimeException('Seules les factures overdue peuvent être relancées.');
    }
        if ($invoice->getStatus() !== 'overdue') {
            throw new \RuntimeException('Seules les factures overdue peuvent être relancées.');
        }

        if (!$invoice->getId()) {
            throw new \RuntimeException('La facture doit être persistée avant de créer une relance.');
        }

        $lastReminder = $this->reminderRepository->findLastByInvoiceId($invoice->getId());
        $level = $lastReminder ? min(3, $lastReminder->getLevel() + 1) : 1;

        $daysLate = $this->calculateDaysLate($invoice);
        [$subject, $message] = $this->generateReminderContent($invoice, $level, $daysLate);

        $reminder = new Reminder();
        $reminder->setInvoice($invoice);
        $reminder->setLevel($level);
        $reminder->setChannel($channel);
        $reminder->setSubject($subject);
        $reminder->setMessage($message);
        $reminder->setStatus('generated');
        $reminder->setCreatedAt(new \DateTimeImmutable());
        $reminder->setUpdatedAt(new \DateTimeImmutable());

        $invoice->addReminder($reminder);
        $invoice->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($reminder);
        $this->em->persist($invoice);
        $this->em->flush();

        return $reminder;
    }

    /**
     * Génère puis envoie réellement la relance par email.
     *
     * Résultat :
     * - status = sent si l'email est parti
     * - status = failed si l'envoi échoue
     */
    public function createAndSendReminderForInvoice(Invoice $invoice, string $channel = 'email'): Reminder
    {
        $reminder = $this->createReminderForInvoice($invoice, $channel);

        try {
            $this->reminderMailerService->sendReminderEmail($reminder);

            $reminder->setStatus('sent');
            $reminder->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($reminder);
            $this->em->flush();
        } catch (TransportExceptionInterface|\Throwable $e) {
            $reminder->setStatus('failed');
            $reminder->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($reminder);
            $this->em->flush();

            throw new \RuntimeException(
                'Relance créée mais envoi email échoué : ' . $e->getMessage()
            );
        }

        return $reminder;
    }

    /**
     * Liste les relances d'une facture.
     */
    public function listRemindersForInvoice(Invoice $invoice): array
    {
        if (!$invoice->getId()) {
            return [];
        }

        return $this->reminderRepository->findByInvoiceId($invoice->getId());
    }

    /**
     * Calcule le nombre de jours de retard.
     */
    private function calculateDaysLate(Invoice $invoice): int
    {
        $dueDate = $invoice->getDueDate();

        if (!$dueDate instanceof \DateTimeInterface) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $interval = $dueDate->diff($now);

        return max(0, (int) $interval->format('%r%a'));
    }

    /**
     * Génère le sujet et le contenu du message selon le niveau de relance.
     */
    private function generateReminderContent(Invoice $invoice, int $level, int $daysLate): array
    {
        $invoiceNumber = $invoice->getNumber();
        $clientName = $invoice->getClient()?->getName() ?? 'Client';
        $remainingAmount = $invoice->getRemainingAmount();
        $dueDate = $invoice->getDueDate()?->format('Y-m-d') ?? 'date inconnue';

        if ($level === 1) {
            $subject = sprintf('Rappel amical - facture %s en retard', $invoiceNumber);
            $message = sprintf(
                "Bonjour %s,\n\nNous vous contactons au sujet de la facture %s, arrivée à échéance le %s.\nLe montant restant dû est de %.2f €.\n\nSauf erreur de notre part, ce règlement semble encore en attente.\nMerci de nous indiquer votre date de paiement prévue.\n\nCordialement,",
                $clientName,
                $invoiceNumber,
                $dueDate,
                $remainingAmount
            );
        } elseif ($level === 2) {
            $subject = sprintf('Deuxième relance - facture %s impayée', $invoiceNumber);
            $message = sprintf(
                "Bonjour %s,\n\nMalgré notre précédent message, la facture %s reste impayée à ce jour.\nElle est en retard de %d jour(s), avec un solde restant de %.2f €.\n\nMerci de procéder au règlement dès que possible ou de nous contacter pour clarifier la situation.\n\nCordialement,",
                $clientName,
                $invoiceNumber,
                $daysLate,
                $remainingAmount
            );
        } else {
            $subject = sprintf('Relance finale - facture %s toujours impayée', $invoiceNumber);
            $message = sprintf(
                "Bonjour %s,\n\nNous vous adressons une relance finale concernant la facture %s.\nCette facture est échue depuis %d jour(s) et le montant restant dû est de %.2f €.\n\nNous vous remercions de régulariser la situation sans délai ou de revenir vers nous rapidement.\n\nCordialement,",
                $clientName,
                $invoiceNumber,
                $daysLate,
                $remainingAmount
            );
        }

        return [$subject, $message];
    }
}