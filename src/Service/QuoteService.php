<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\QuoteItem;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

class QuoteService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function createQuote(
        Tenant $tenant,
        Client $client,
        array $itemsData,
        ?string $expiresAt = null,
        float $taxRate = 0
    ): Quote {
        $quote = new Quote();
        $quote->setTenant($tenant);
        $quote->setClient($client);
        $quote->setNumber($this->generateQuoteNumber());
        $quote->setStatus('draft');
        $quote->setCreatedAt(new \DateTimeImmutable());
        $quote->setUpdatedAt(new \DateTimeImmutable());

        if ($expiresAt) {
            $quote->setExpiresAt(new \DateTimeImmutable($expiresAt));
        }

        $subtotal = 0;

        foreach ($itemsData as $itemData) {
            $quantity = (float) ($itemData['quantity'] ?? 0);
            $unitPrice = (float) ($itemData['unitPrice'] ?? 0);
            $lineTotal = $quantity * $unitPrice;

            $item = new QuoteItem();
            $item->setLabel($itemData['label'] ?? 'Article');
            $item->setQuantity($quantity);
            $item->setUnitPrice($unitPrice);
            $item->setLineTotal($lineTotal);

            $quote->addItem($item);

            $subtotal += $lineTotal;
        }

        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $quote->setSubtotal($subtotal);
        $quote->setTaxAmount($taxAmount);
        $quote->setTotal($total);

        $this->em->persist($quote);
        $this->em->flush();

        return $quote;
    }

    private function generateQuoteNumber(): string
    {
        return 'DEV-' . date('Ymd-His');
    }
}