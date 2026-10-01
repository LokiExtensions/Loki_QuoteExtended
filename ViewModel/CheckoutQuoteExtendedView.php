<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\ViewModel;

use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CheckoutQuoteExtendedView implements ArgumentInterface
{
    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository,
        private readonly CheckoutState $checkoutState
    ) {
    }

    public function getValue(string $column): string
    {
        try {
            $quoteExtended = $this->quoteExtendedRepository->getByQuoteId($this->checkoutState->getQuoteId());
        } catch (NoSuchEntityException) {
            return '';
        }

        return (string)$quoteExtended->getData($column);
    }
}
