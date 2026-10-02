<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Model\ResourceModel\QuoteExtended;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'loki_quote_extended_collection';

    protected function _construct(): void
    {
        $this->_init(QuoteExtended::class, QuoteExtendedResource::class);
    }
}
