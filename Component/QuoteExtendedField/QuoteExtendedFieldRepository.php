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
        $fieldName = $this->getFieldName();
        if (in_array($fieldName, QuoteExtendedRepository::RESERVED_COLUMNS, true)) {
            return;
        }

        $quoteId = $this->checkoutState->getQuoteId();
        if ($quoteId < 1) {
            return;
        }

        $quoteExtended = $this->quoteExtendedRepository->getOrCreateByQuoteId($quoteId);
        $quoteExtended->setData($fieldName, is_scalar($value) ? $value : null);
        $this->quoteExtendedRepository->save($quoteExtended);
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
