<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\ViewModel\Adminhtml;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Loki\QuoteExtended\ViewModel\Adminhtml\OrderQuoteExtendedView;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderQuoteExtendedViewTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;
    private RequestInterface&MockObject $request;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private InvoiceRepositoryInterface&MockObject $invoiceRepository;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
    }

    public function testGetValueUsesOrderIdParam(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->method('get')->with(7)->willReturn($order);
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValuePrefersInvoiceIdParam(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getOrder')->willReturn($order);

        $this->setParams(['invoice_id' => '3', 'order_id' => '7']);
        $this->invoiceRepository->method('get')->with(3)->willReturn($invoice);
        $this->orderRepository->expects($this->never())->method('get');
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWithoutParams(): void
    {
        $this->setParams([]);
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWhenOrderDoesNotExist(): void
    {
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWhenInvoiceDoesNotExist(): void
    {
        $this->setParams(['invoice_id' => '3']);
        $this->invoiceRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWithoutRow(): void
    {
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->method('get')->willReturn($this->createMock(OrderInterface::class));
        $this->quoteExtendedRepository->method('getByOrder')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    public function testRowIsLoadedOnlyOnce(): void
    {
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->expects($this->once())->method('get')
            ->willReturn($this->createMock(OrderInterface::class));
        $this->quoteExtendedRepository->expects($this->once())->method('getByOrder')
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar', 'other_field' => 'baz']));

        $viewModel = $this->createViewModel();

        $this->assertSame('foobar', $viewModel->getValue('example_field'));
        $this->assertSame('baz', $viewModel->getValue('other_field'));
    }

    private function setParams(array $params): void
    {
        $this->request->method('getParam')->willReturnCallback(
            fn (string $name) => $params[$name] ?? null
        );
    }

    private function createViewModel(): OrderQuoteExtendedView
    {
        return new OrderQuoteExtendedView(
            $this->quoteExtendedRepository,
            $this->request,
            $this->orderRepository,
            $this->invoiceRepository
        );
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
