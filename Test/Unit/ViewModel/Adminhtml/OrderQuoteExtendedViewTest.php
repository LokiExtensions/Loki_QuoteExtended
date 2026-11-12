<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\ViewModel\Adminhtml;

use Loki\QuoteExtended\Config\LabelledColumns;
use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Loki\QuoteExtended\ViewModel\Adminhtml\OrderQuoteExtendedView;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderQuoteExtendedViewTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;
    private RequestInterface&MockObject $request;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private InvoiceRepositoryInterface&MockObject $invoiceRepository;
    private ShipmentRepositoryInterface&MockObject $shipmentRepository;
    private CreditmemoRepositoryInterface&MockObject $creditmemoRepository;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->creditmemoRepository = $this->createMock(CreditmemoRepositoryInterface::class);
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

    public function testGetValueUsesShipmentIdParam(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrder')->willReturn($order);

        $this->setParams(['shipment_id' => '5']);
        $this->shipmentRepository->method('get')->with(5)->willReturn($shipment);
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueUsesCreditmemoIdParam(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrder')->willReturn($order);

        $this->setParams(['creditmemo_id' => '4']);
        $this->creditmemoRepository->method('get')->with(4)->willReturn($creditmemo);
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValuePrefersShipmentIdOverOtherParams(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrder')->willReturn($order);

        $this->setParams(['shipment_id' => '5', 'creditmemo_id' => '4', 'invoice_id' => '3', 'order_id' => '7']);
        $this->shipmentRepository->expects($this->once())->method('get')->with(5)->willReturn($shipment);
        $this->creditmemoRepository->expects($this->never())->method('get');
        $this->invoiceRepository->expects($this->never())->method('get');
        $this->orderRepository->expects($this->never())->method('get');
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValuePrefersCreditmemoIdOverInvoiceAndOrderParams(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getOrder')->willReturn($order);

        $this->setParams(['creditmemo_id' => '4', 'invoice_id' => '3', 'order_id' => '7']);
        $this->creditmemoRepository->expects($this->once())->method('get')->with(4)->willReturn($creditmemo);
        $this->invoiceRepository->expects($this->never())->method('get');
        $this->orderRepository->expects($this->never())->method('get');
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame('foobar', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWhenShipmentDoesNotExist(): void
    {
        $this->setParams(['shipment_id' => '5', 'order_id' => '7']);
        $this->shipmentRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->orderRepository->expects($this->never())->method('get');
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
    }

    public function testGetValueReturnsEmptyStringWhenCreditmemoDoesNotExist(): void
    {
        $this->setParams(['creditmemo_id' => '4']);
        $this->creditmemoRepository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $this->assertSame('', $this->createViewModel()->getValue('example_field'));
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

    public function testGetLabelledValuesReturnsRegisteredColumnsWithValues(): void
    {
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->method('get')->willReturn($this->createMock(OrderInterface::class));
        $this->quoteExtendedRepository->method('getByOrder')->willReturn($this->createQuoteExtended([
            'entity_id' => 1,
            'order_id' => 7,
            'example_field' => 'foobar',
            'number_field' => 12,
            'empty_field' => '',
            'null_field' => null,
            'unregistered_field' => 'hidden',
        ]));

        $viewModel = $this->createViewModel([
            'number_field' => 'Number',
            'example_field' => 'Example Field',
            'empty_field' => 'Empty',
            'null_field' => 'Null',
            'missing_field' => 'Missing',
            'order_id' => 'Order ID',
        ]);

        $this->assertSame([
            ['label' => 'Number', 'value' => '12'],
            ['label' => 'Example Field', 'value' => 'foobar'],
        ], $viewModel->getLabelledValues());
    }

    public function testGetLabelledValuesReturnsEmptyArrayWithoutColumns(): void
    {
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->method('get')->willReturn($this->createMock(OrderInterface::class));
        $this->quoteExtendedRepository->method('getByOrder')
            ->willReturn($this->createQuoteExtended(['example_field' => 'foobar']));

        $this->assertSame([], $this->createViewModel()->getLabelledValues());
    }

    public function testGetLabelledValuesReturnsEmptyArrayWithoutRow(): void
    {
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->method('get')->willReturn($this->createMock(OrderInterface::class));
        $this->quoteExtendedRepository->method('getByOrder')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $viewModel = $this->createViewModel(['example_field' => 'Example Field', 'other_field' => 'Other']);

        $this->assertSame([], $viewModel->getLabelledValues());
    }

    public function testGetLabelledValuesLoadsRowOnlyOnce(): void
    {
        $this->setParams(['order_id' => '7']);
        $this->orderRepository->expects($this->once())->method('get')
            ->willReturn($this->createMock(OrderInterface::class));
        $this->quoteExtendedRepository->expects($this->once())->method('getByOrder')
            ->willReturn($this->createQuoteExtended(['example_field' => 'foo', 'other_field' => 'bar']));

        $viewModel = $this->createViewModel(['example_field' => 'Example Field', 'other_field' => 'Other']);

        $this->assertSame([
            ['label' => 'Example Field', 'value' => 'foo'],
            ['label' => 'Other', 'value' => 'bar'],
        ], $viewModel->getLabelledValues());
    }

    private function setParams(array $params): void
    {
        $this->request->method('getParam')->willReturnCallback(
            fn (string $name) => $params[$name] ?? null
        );
    }

    private function createViewModel(array $columns = []): OrderQuoteExtendedView
    {
        return new OrderQuoteExtendedView(
            $this->quoteExtendedRepository,
            $this->request,
            $this->orderRepository,
            $this->invoiceRepository,
            $this->shipmentRepository,
            $this->creditmemoRepository,
            new LabelledColumns($columns)
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
