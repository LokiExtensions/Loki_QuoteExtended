<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Model;

use Loki\QuoteExtended\Api\Data\QuoteExtendedValueInterface;
use Loki\QuoteExtended\Model\QuoteExtendedValue;
use PHPUnit\Framework\TestCase;

class QuoteExtendedValueTest extends TestCase
{
    public function testReturnsCodeAndValue(): void
    {
        $value = new QuoteExtendedValue('example_field', 'foobar');

        $this->assertInstanceOf(QuoteExtendedValueInterface::class, $value);
        $this->assertSame('example_field', $value->getCode());
        $this->assertSame('foobar', $value->getValue());
    }

    public function testDefaults(): void
    {
        $value = new QuoteExtendedValue();

        $this->assertSame('', $value->getCode());
        $this->assertNull($value->getValue());
    }

    public function testKeepsNullValue(): void
    {
        $value = new QuoteExtendedValue('example_field', null);

        $this->assertSame('example_field', $value->getCode());
        $this->assertNull($value->getValue());
    }
}
