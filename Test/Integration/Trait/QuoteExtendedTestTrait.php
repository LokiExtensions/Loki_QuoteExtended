<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Trait;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

trait QuoteExtendedTestTrait
{
    protected function getQuoteExtendedRepository(): QuoteExtendedRepository
    {
        return $this->om()->get(QuoteExtendedRepository::class);
    }

    protected function saveQuoteExtended(int $quoteId, array $data = []): QuoteExtended
    {
        $repository = $this->getQuoteExtendedRepository();
        $quoteExtended = $repository->getOrCreateByQuoteId($quoteId);
        foreach ($data as $key => $value) {
            $quoteExtended->setData($key, $value);
        }

        return $repository->save($quoteExtended);
    }

    protected function loadQuoteExtended(int $entityId): QuoteExtended
    {
        $quoteExtended = $this->om()->create(QuoteExtended::class);
        $this->om()->get(QuoteExtendedResource::class)->load($quoteExtended, $entityId);

        return $quoteExtended;
    }

    protected function placeOrder(int $cartId): OrderInterface
    {
        $paymentMethod = $this->om()->get(PaymentMethodManagementInterface::class)->get($cartId);
        $orderId = (int)$this->om()->get(CartManagementInterface::class)->placeOrder($cartId, $paymentMethod);

        return $this->om()->create(OrderRepositoryInterface::class)->get($orderId);
    }

    protected function restoreQuote(int $cartId): void
    {
        $cartRepository = $this->om()->create(CartRepositoryInterface::class);
        $quote = $cartRepository->get($cartId);
        $quote->setIsActive(true);
        $quote->setReservedOrderId(null);
        $cartRepository->save($quote);
    }

    protected function createInvoice(OrderInterface $order): InvoiceInterface
    {
        $invoiceId = $this->om()->get(InvoiceOrderInterface::class)->execute((int)$order->getEntityId());

        return $this->om()->create(InvoiceRepositoryInterface::class)->get((int)$invoiceId);
    }

    private function om(): ObjectManagerInterface
    {
        return Bootstrap::getObjectManager();
    }
}
