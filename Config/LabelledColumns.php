<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Config;

use Loki\QuoteExtended\Repository\QuoteExtendedRepository;

class LabelledColumns
{
    public function __construct(
        private readonly array $columns = []
    ) {
    }

    public function getColumns(): array
    {
        $columns = [];
        foreach ($this->columns as $column => $label) {
            $column = (string)$column;
            if ($column === '' || in_array($column, QuoteExtendedRepository::RESERVED_COLUMNS, true)) {
                continue;
            }

            $columns[$column] = (string)$label;
        }

        return $columns;
    }
}
