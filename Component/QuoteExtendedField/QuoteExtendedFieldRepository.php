<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Component\QuoteExtendedField;

use Loki\Field\Component\Base\Field\FieldRepository;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;

class QuoteExtendedFieldRepository extends FieldRepository
{
    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository,
        private readonly CheckoutState $checkoutState
    ) {
    }

    public function getValue(): mixed
    {
        try {
            $quoteExtended = $this->quoteExtendedRepository->getByQuoteId($this->checkoutState->getQuoteId());
        } catch (NoSuchEntityException) {
            return '';
        }

        return (string)$quoteExtended->getData($this->getFieldName());
    }

    public function saveValue(mixed $value): void
    {
        $this->quoteExtendedRepository->saveValue(
            $this->checkoutState->getQuoteId(),
            $this->getFieldName(),
            $value
        );
    }

    public function getFieldName(): string
    {
        $fieldName = (string)$this->getComponent()->getBlock()?->getFieldName();
        if ($fieldName !== '') {
            return $fieldName;
        }

        return parent::getFieldName();
    }
}
