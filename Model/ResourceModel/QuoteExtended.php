<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteExtended extends AbstractDb
{
    public const TABLE_NAME = 'loki_quote_extended';
    public const PRIMARY_KEY = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }

    public function deleteOrphanedRows(): int
    {
        return $this->getConnection()->delete(
            $this->getMainTable(),
            ['quote_id IS NULL', 'order_id IS NULL']
        );
    }
}
