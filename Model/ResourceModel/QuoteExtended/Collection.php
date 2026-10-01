<?php
declare(strict_types=1);

namespace Loki\QuoteExtended\Model\ResourceModel\QuoteExtended;

use Loki\QuoteExtended\Model\QuoteExtended as Model;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
