<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Model;

use Loki\QuoteExtended\Model\QuoteExtended;
use PHPUnit\Framework\TestCase;

class QuoteExtendedTest extends TestCase
{
    public function testIdsAreNullWhenUnset(): void
    {
        $quoteExtended = $this->createQuoteExtended([]);

        $this->assertNull($quoteExtended->getQuoteId());
        $this->assertNull($quoteExtended->getOrderId());
    }

    public function testIdsAreCastToInteger(): void
    {
        $quoteExtended = $this->createQuoteExtended(['quote_id' => '42', 'order_id' => '7']);

        $this->assertSame(42, $quoteExtended->getQuoteId());
        $this->assertSame(7, $quoteExtended->getOrderId());
    }

    public function testSettersAcceptIntegerAndNull(): void
    {
        $quoteExtended = $this->createQuoteExtended([]);

        $this->assertSame($quoteExtended, $quoteExtended->setQuoteId(42));
        $this->assertSame($quoteExtended, $quoteExtended->setOrderId(7));
        $this->assertSame(42, $quoteExtended->getQuoteId());
        $this->assertSame(7, $quoteExtended->getOrderId());

        $quoteExtended->setQuoteId(null);
        $quoteExtended->setOrderId(null);
        $this->assertNull($quoteExtended->getQuoteId());
        $this->assertNull($quoteExtended->getOrderId());
    }

    public function testGetValuesStripsReservedColumns(): void
    {
        $quoteExtended = $this->createQuoteExtended([
            'entity_id' => 1,
            'quote_id' => 42,
            'order_id' => 7,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'example_field' => 'foobar',
            'empty_field' => null,
        ]);

        $this->assertSame(['example_field' => 'foobar', 'empty_field' => null], $quoteExtended->getValues());
    }

    public function testGetValuesReturnsEmptyArrayWithoutValues(): void
    {
        $this->assertSame([], $this->createQuoteExtended([])->getValues());
        $this->assertSame([], $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 42])->getValues());
    }

    private function createQuoteExtended(array $data): QuoteExtended
    {
        $quoteExtended = $this->getMockBuilder(QuoteExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteExtended->setData($data);

        return $quoteExtended;
    }
}
