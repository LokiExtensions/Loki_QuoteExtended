<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\ViewModel;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Loki\QuoteExtended\ViewModel\OrderQuoteExtendedView;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderQuoteExtendedViewTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;
    private Registry&MockObject $registry;
    private CheckoutSession&MockObject $checkoutSession;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
        $this->registry = $this->createMock(Registry::class);
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLastRealOrder'])
            ->getMock();
    }

    public function testGetValueUsesCurrentOrderFromRegistry(): void
    {
        $order = $this->createOrder(7);
        $this->registry->method('registry')->with('current_order')->willReturn($order);
        $this->checkoutSession->expects($this->never())->method('getLastRealOrder');
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar', 'number_field' => 12]));

        $viewModel = $this->createViewModel();

        $this->assertSame('foobar', $viewModel->getValue('example_field'));
        $this->assertSame('12', $viewModel->getValue('number_field'));
    }

    public function testGetValueFallsBackToLastRealOrder(): void
    {
        $order = $this->createOrder(8);
        $this->registry->method('registry')->with('current_order')->willReturn(null);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueFallsBackToLastRealOrderWhenRegistryOrderHasNoId(): void
    {
        $order = $this->createOrder(8);
        $this->registry->method('registry')->willReturn($this->createOrder(0));
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWithoutOrder(): void
    {
        $this->registry->method('registry')->willReturn(null);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($this->createOrder(0));
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWithoutRow(): void
    {
        $this->registry->method('registry')->willReturn($this->createOrder(7));
        $this->quoteExtendedRepository->method('getByOrder')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringForUnknownColumn(): void
    {
        $this->registry->method('registry')->willReturn($this->createOrder(7));
        $this->quoteExtendedRepository->method('getByOrder')
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('', $this->createViewModel()->getValue('unknown_field'));
    }

    public function testRowIsLoadedOnlyOnce(): void
    {
        $this->registry->expects($this->once())->method('registry')->willReturn($this->createOrder(7));
        $this->quoteExtendedRepository->expects($this->once())->method('getByOrder')
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar', 'other_field' => 'baz']));

        $viewModel = $this->createViewModel();

        $this->assertSame('foobar', $viewModel->getValue('example_field'));
        $this->assertSame('baz', $viewModel->getValue('other_field'));
    }

    private function createViewModel(): OrderQuoteExtendedView
    {
        return new OrderQuoteExtendedView($this->quoteExtendedRepository, $this->registry, $this->checkoutSession);
    }

    private function createOrder(int $orderId): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn($orderId);

        return $order;
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
