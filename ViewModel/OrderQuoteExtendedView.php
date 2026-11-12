<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\ViewModel;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;

class OrderQuoteExtendedView implements ArgumentInterface
{
    private ?QuoteExtended $quoteExtended = null;

    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository,
        private readonly Registry $registry,
        private readonly CheckoutSession $checkoutSession
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
        $order = $this->registry->registry('current_order');
        if ($order instanceof OrderInterface && (int)$order->getEntityId() > 0) {
            return $order;
        }

        $order = $this->checkoutSession->getLastRealOrder();
        if ((int)$order->getEntityId() > 0) {
            return $order;
        }

        return null;
    }
}
