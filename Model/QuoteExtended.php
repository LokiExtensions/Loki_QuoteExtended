<?php
declare(strict_types=1);

namespace Loki\QuoteExtended\Model;

use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class QuoteExtended extends AbstractModel
{
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
    }
}
