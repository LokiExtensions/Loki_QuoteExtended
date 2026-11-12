<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteExtended extends AbstractDb
{
    public const TABLE_NAME = 'loki_quote_extended';
    public const PRIMARY_KEY = 'entity_id';

    private ?array $columns = null;

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

    public function upsertValue(int $quoteId, string $column, mixed $value): void
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            ['quote_id' => $quoteId, $column => $value],
            [$column]
        );
    }

    public function hasColumn(string $column): bool
    {
        return array_key_exists($column, $this->getColumns());
    }

    public function getColumnLength(string $column): ?int
    {
        $definition = $this->getColumns()[$column] ?? [];
        $dataType = strtolower((string)($definition['DATA_TYPE'] ?? ''));
        if (!str_contains($dataType, 'char') && !str_contains($dataType, 'text')) {
            return null;
        }

        $length = (int)($definition['LENGTH'] ?? 0);
        return $length > 0 ? $length : null;
    }

    private function getColumns(): array
    {
        if ($this->columns === null) {
            $this->columns = $this->getConnection()->describeTable($this->getMainTable());
        }

        return $this->columns;
    }
}
