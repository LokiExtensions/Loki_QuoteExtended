<?php
<<<<<<< HEAD
=======

>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
declare(strict_types=1);

namespace Loki\QuoteExtended\Model;

<<<<<<< HEAD
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as ResourceModel;
=======
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
use Magento\Framework\Model\AbstractModel;

class QuoteExtended extends AbstractModel
{
<<<<<<< HEAD
    public const FIELD_QUOTE_ID = 'quote_id';
    public const FIELD_CREATED_AT = 'created_at';
    public const FIELD_UPDATED_AT = 'updated_at';

    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    public function getQuoteId(): int
    {
        return (int)$this->getData(self::FIELD_QUOTE_ID);
    }

    public function setQuoteId(int $quoteId): self
    {
        $this->setData(self::FIELD_QUOTE_ID, $quoteId);

        return $this;
    }

    public function getCreatedAt(): string
    {
        return (string)$this->getData(self::FIELD_CREATED_AT);
    }

    public function getUpdatedAt(): string
    {
        return (string)$this->getData(self::FIELD_UPDATED_AT);
=======
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
>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
    }
}
