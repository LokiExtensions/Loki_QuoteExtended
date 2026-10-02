<?php
<<<<<<< HEAD
=======

>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
declare(strict_types=1);

namespace Loki\QuoteExtended\Model\ResourceModel\QuoteExtended;

<<<<<<< HEAD
use Loki\QuoteExtended\Model\QuoteExtended as Model;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as ResourceModel;
=======
use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
<<<<<<< HEAD
    protected $_idFieldName = 'id';

    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
=======
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'loki_quote_extended_collection';

    protected function _construct(): void
    {
        $this->_init(QuoteExtended::class, QuoteExtendedResource::class);
>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
    }
}
