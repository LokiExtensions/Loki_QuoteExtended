<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Config;

use Loki\QuoteExtended\Config\LabelledColumns;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use PHPUnit\Framework\TestCase;

class LabelledColumnsTest extends TestCase
{
    public function testGetColumnsReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], (new LabelledColumns())->getColumns());
    }

    public function testGetColumnsKeepsOrderAndCastsLabels(): void
    {
        $labelledColumns = new LabelledColumns([
            'number_field' => 'Number',
            'example_field' => 'Example Field',
            'numeric_label' => 12,
        ]);

        $this->assertSame([
            'number_field' => 'Number',
            'example_field' => 'Example Field',
            'numeric_label' => '12',
        ], $labelledColumns->getColumns());
    }

    public function testGetColumnsDropsEmptyColumns(): void
    {
        $labelledColumns = new LabelledColumns([
            '' => 'Empty',
            'example_field' => 'Example Field',
        ]);

        $this->assertSame(['example_field' => 'Example Field'], $labelledColumns->getColumns());
    }

    public function testGetColumnsDropsReservedColumns(): void
    {
        $columns = array_fill_keys(QuoteExtendedRepository::RESERVED_COLUMNS, 'Reserved');
        $columns['example_field'] = 'Example Field';

        $this->assertSame(['example_field' => 'Example Field'], (new LabelledColumns($columns))->getColumns());
    }
}
