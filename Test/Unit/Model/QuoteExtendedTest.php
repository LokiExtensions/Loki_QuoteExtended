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
