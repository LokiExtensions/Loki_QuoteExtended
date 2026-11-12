<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Model;

use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\Model\AbstractModel;

class QuoteExtended extends AbstractModel
{
    protected $_eventPrefix = 'loki_quote_extended';

    protected function _construct(): void
    {
        $this->_init(QuoteExtendedResource::class);
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData('quote_id');
        return $value !== null ? (int)$value : null;
    }

    public function setQuoteId(?int $quoteId): self
    {
        return $this->setData('quote_id', $quoteId);
    }

    public function getOrderId(): ?int
    {
        $value = $this->getData('order_id');
        return $value !== null ? (int)$value : null;
    }

    public function setOrderId(?int $orderId): self
    {
        return $this->setData('order_id', $orderId);
    }

    public function getValues(): array
    {
        return array_diff_key($this->getData(), array_flip(QuoteExtendedRepository::RESERVED_COLUMNS));
    }
}
