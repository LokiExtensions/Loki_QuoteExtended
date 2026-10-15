<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\ViewModel;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Loki\QuoteExtended\ViewModel\CheckoutQuoteExtendedView;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckoutQuoteExtendedViewTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;
    private CheckoutState&MockObject $checkoutState;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
        $this->checkoutState = $this->createMock(CheckoutState::class);
        $this->checkoutState->method('getQuoteId')->willReturn(42);
    }

    public function testGetValueReturnsColumnValue(): void
    {
        $this->quoteExtendedRepository->method('getByQuoteId')->with(42)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar', 'number_field' => 12]));

        $viewModel = $this->createViewModel();

        $this->assertSame('foobar', $viewModel->getValue('example_field'));
        $this->assertSame('12', $viewModel->getValue('number_field'));
    }

    public function testGetValueReturnsEmptyStringForUnknownColumn(): void
    {
        $this->quoteExtendedRepository->method('getByQuoteId')
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('', $this->createViewModel()->getValue('unknown_field'));
    }

    public function testGetValueReturnsEmptyStringWithoutRow(): void
    {
        $this->quoteExtendedRepository->method('getByQuoteId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    private function createViewModel(): CheckoutQuoteExtendedView
    {
        return new CheckoutQuoteExtendedView($this->quoteExtendedRepository, $this->checkoutState);
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
