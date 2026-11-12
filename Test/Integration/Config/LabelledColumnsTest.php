<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Config;

use Loki\QuoteExtended\Config\LabelledColumns;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class LabelledColumnsTest extends TestCase
{
    public function testGetColumnsReturnsConfiguredColumns(): void
    {
        $labelledColumns = Bootstrap::getObjectManager()->create(LabelledColumns::class, [
            'columns' => ['foo' => 'Foo', 'bar' => 'Bar'],
        ]);

        $this->assertSame(['foo' => 'Foo', 'bar' => 'Bar'], $labelledColumns->getColumns());
    }

    public function testGetColumnsDropsEmptyAndReservedColumns(): void
    {
        $labelledColumns = Bootstrap::getObjectManager()->create(LabelledColumns::class, [
            'columns' => [
                '' => 'Empty',
                'entity_id' => 'Entity',
                'quote_id' => 'Quote',
                'order_id' => 'Order',
                'created_at' => 'Created',
                'updated_at' => 'Updated',
                'foo' => 'Foo',
            ],
        ]);

        $this->assertSame(['foo' => 'Foo'], $labelledColumns->getColumns());
    }

    public function testGetColumnsWithoutConfigurationIsEmpty(): void
    {
        $labelledColumns = Bootstrap::getObjectManager()->create(LabelledColumns::class, ['columns' => []]);

        $this->assertSame([], $labelledColumns->getColumns());
    }
}
