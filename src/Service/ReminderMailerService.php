<?php

namespace App\Service;

use App\Entity\Reminder;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ReminderMailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName
    ) {
    }

    public function sendReminderEmail(Reminder $reminder): void
    {
        $invoice = $reminder->getInvoice();
        $client = $invoice?->getClient();
        $clientEmail = $client?->getEmail();

        if (!$invoice) {
            throw new \RuntimeException('La relance n\'est liée à aucune facture.');
        }

        if (!$clientEmail) {
            throw new \RuntimeException('Le client n\'a pas d\'adresse email renseignée.');
        }

        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($clientEmail)
            ->subject($reminder->getSubject())
            ->text($this->buildTextBody($reminder))
            ->html($this->buildHtmlBody($reminder));

        $this->mailer->send($email);
    }

    private function buildTextBody(Reminder $reminder): string
    {
        $invoice = $reminder->getInvoice();
        $clientName = $invoice?->getClient()?->getName() ?? 'Client';
        $invoiceNumber = $invoice?->getNumber() ?? 'N/A';
        $remainingAmount = $invoice?->getRemainingAmount() ?? 0;
        $dueDate = $invoice?->getDueDate()?->format('Y-m-d') ?? 'date inconnue';

        return sprintf(
            "Bonjour %s,\n\n%s\n\nRécapitulatif :\n- Facture : %s\n- Montant restant dû : %.2f €\n- Date d'échéance : %s\n\nMerci de votre retour.\n",
            $clientName,
            $reminder->getMessage(),
            $invoiceNumber,
            $remainingAmount,
            $dueDate
        );
    }

    private function buildHtmlBody(Reminder $reminder): string
    {
        $invoice = $reminder->getInvoice();
        $clientName = htmlspecialchars($invoice?->getClient()?->getName() ?? 'Client', ENT_QUOTES);
        $invoiceNumber = htmlspecialchars($invoice?->getNumber() ?? 'N/A', ENT_QUOTES);
        $remainingAmount = number_format((float) ($invoice?->getRemainingAmount() ?? 0), 2, ',', ' ');
        $dueDate = htmlspecialchars($invoice?->getDueDate()?->format('Y-m-d') ?? 'date inconnue', ENT_QUOTES);
        $message = nl2br(htmlspecialchars($reminder->getMessage(), ENT_QUOTES));

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Relance facture</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.6;">
    <p>Bonjour {$clientName},</p>
    <p>{$message}</p>

    <h3>Récapitulatif</h3>
    <ul>
        <li><strong>Facture :</strong> {$invoiceNumber}</li>
        <li><strong>Montant restant dû :</strong> {$remainingAmount} €</li>
        <li><strong>Date d'échéance :</strong> {$dueDate}</li>
    </ul>

    <p>Merci de votre retour.</p>
</body>
</html>
HTML;
    }
}