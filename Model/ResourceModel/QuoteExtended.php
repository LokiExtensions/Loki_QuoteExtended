<?php
declare(strict_types=1);

namespace Loki\QuoteExtended\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteExtended extends AbstractDb
{
    protected $connectionName = 'checkout';

    protected function _construct(): void
    {
        $this->_init('loki_quote_extended', 'id');
    }
}
