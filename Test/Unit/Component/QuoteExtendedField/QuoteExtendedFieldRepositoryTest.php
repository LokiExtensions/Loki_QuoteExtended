<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Component\QuoteExtendedField;

use Loki\Components\Component\ComponentInterface;
use Loki\QuoteExtended\Component\QuoteExtendedField\QuoteExtendedFieldRepository;
use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteExtendedFieldRepositoryTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;
    private CheckoutState&MockObject $checkoutState;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
        $this->checkoutState = $this->createMock(CheckoutState::class);
    }

    public function testGetFieldNameUsesLastPartOfComponentName(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.example_field');

        $this->assertSame('example_field', $repository->getFieldName());
    }

    public function testGetFieldNamePrefersFieldNameArgument(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.example_field', 'other_field');

        $this->assertSame('other_field', $repository->getFieldName());
    }

    public function testSaveValueRefusesReservedColumns(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quoteExtendedRepository->expects($this->never())->method('save');

        $repository = $this->createRepository('loki-checkout.customer.order_id');
        $repository->saveValue('1');
    }

    public function testGetValueReturnsColumnValue(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $quoteExtended = $this->createQuoteExtended(['quote_id' => 42, 'example_field' => 'foobar']);
        $this->quoteExtendedRepository->method('getByQuoteId')->with(42)->willReturn($quoteExtended);

        $repository = $this->createRepository('loki-checkout.customer.example_field');

        $this->assertSame('foobar', $repository->getValue());
    }

    public function testGetValueReturnsEmptyStringWithoutRow(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quoteExtendedRepository->method('getByQuoteId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $repository = $this->createRepository('loki-checkout.customer.example_field');

        $this->assertSame('', $repository->getValue());
    }

    public function testSaveValueStoresColumnValue(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $quoteExtended = $this->createQuoteExtended(['quote_id' => 42]);
        $this->quoteExtendedRepository->method('getOrCreateByQuoteId')->with(42)->willReturn($quoteExtended);
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($quoteExtended);

        $repository = $this->createRepository('loki-checkout.customer.example_field');
        $repository->saveValue('foobar');

        $this->assertSame('foobar', $quoteExtended->getData('example_field'));
    }

    public function testSaveValueDoesNothingWithoutQuote(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(0);
        $this->quoteExtendedRepository->expects($this->never())->method('getOrCreateByQuoteId');
        $this->quoteExtendedRepository->expects($this->never())->method('save');

        $repository = $this->createRepository('loki-checkout.customer.example_field');
        $repository->saveValue('foobar');
    }

    public function testSaveValueStoresNullForNonScalarValue(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $quoteExtended = $this->createQuoteExtended(['quote_id' => 42, 'example_field' => 'old']);
        $this->quoteExtendedRepository->method('getOrCreateByQuoteId')->willReturn($quoteExtended);
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($quoteExtended);

        $repository = $this->createRepository('loki-checkout.customer.example_field');
        $repository->saveValue(['foo' => 'bar']);

        $this->assertNull($quoteExtended->getData('example_field'));
    }

    public function testGetValueCastsValueToString(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $this->quoteExtendedRepository->method('getByQuoteId')
            ->willReturn($this->createQuoteExtended(['quote_id' => 42, 'example_field' => 12]));

        $repository = $this->createRepository('loki-checkout.customer.example_field');

        $this->assertSame('12', $repository->getValue());
    }

    public function testSaveValueUsesFieldNameArgumentAsColumn(): void
    {
        $this->checkoutState->method('getQuoteId')->willReturn(42);
        $quoteExtended = $this->createQuoteExtended(['quote_id' => 42]);
        $this->quoteExtendedRepository->method('getOrCreateByQuoteId')->willReturn($quoteExtended);

        $repository = $this->createRepository('loki-checkout.customer.example_field', 'other_field');
        $repository->saveValue('foobar');

        $this->assertSame('foobar', $quoteExtended->getData('other_field'));
        $this->assertFalse($quoteExtended->hasData('example_field'));
    }

    private function createRepository(string $componentName, string $fieldName = ''): QuoteExtendedFieldRepository
    {
        $component = $this->createMock(ComponentInterface::class);
        $component->method('getName')->willReturn($componentName);
        $component->method('getBlock')->willReturn($this->createBlock($fieldName));

        $repository = new QuoteExtendedFieldRepository($this->quoteExtendedRepository, $this->checkoutState);
        $repository->setComponent($component);

        return $repository;
    }

    private function createBlock(string $fieldName): AbstractBlock
    {
        $block = $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $block->setData('field_name', $fieldName);

        return $block;
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
