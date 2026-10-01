<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\ViewModel\Adminhtml;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderQuoteExtendedView implements ArgumentInterface
{
    private ?QuoteExtended $quoteExtended = null;

    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository
    ) {
    }

    public function getValue(string $column): string
    {
        $quoteExtended = $this->getQuoteExtended();
        if (null === $quoteExtended) {
            return '';
        }

        return (string)$quoteExtended->getData($column);
    }

    private function getQuoteExtended(): ?QuoteExtended
    {
        if ($this->quoteExtended instanceof QuoteExtended) {
            return $this->quoteExtended;
        }

        $order = $this->getOrder();
        if (null === $order) {
            return null;
        }

        try {
            $this->quoteExtended = $this->quoteExtendedRepository->getByOrder($order);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $this->quoteExtended;
    }

    private function getOrder(): ?OrderInterface
    {
        try {
            $invoiceId = (int)$this->request->getParam('invoice_id');
            if ($invoiceId > 0) {
                return $this->invoiceRepository->get($invoiceId)->getOrder();
            }

            $orderId = (int)$this->request->getParam('order_id');
            if ($orderId > 0) {
                return $this->orderRepository->get($orderId);
            }
        } catch (NoSuchEntityException) {
            return null;
        }

        return null;
    }
}
