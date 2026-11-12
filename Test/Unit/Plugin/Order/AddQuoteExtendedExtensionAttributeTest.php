<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Plugin\Order;

use Loki\QuoteExtended\Api\Data\QuoteExtendedValueInterface;
use Loki\QuoteExtended\Api\Data\QuoteExtendedValueInterfaceFactory;
use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\QuoteExtendedValue;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended\Collection;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended\CollectionFactory;
use Loki\QuoteExtended\Plugin\Order\AddQuoteExtendedExtensionAttribute;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddQuoteExtendedExtensionAttributeTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;
    private CollectionFactory&MockObject $collectionFactory;
    private OrderExtensionFactory&MockObject $orderExtensionFactory;
    private QuoteExtendedValueInterfaceFactory&MockObject $quoteExtendedValueFactory;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->orderExtensionFactory = $this->createMock(OrderExtensionFactory::class);
        $this->quoteExtendedValueFactory = $this->createMock(QuoteExtendedValueInterfaceFactory::class);
        $this->quoteExtendedValueFactory->method('create')->willReturnCallback(
            fn (array $data) => new QuoteExtendedValue($data['code'], $data['value'])
        );
    }

    public function testAfterGetSkipsOrderWithoutId(): void
    {
        $order = $this->createOrder(0);
        $order->expects($this->never())->method('setExtensionAttributes');
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $this->assertSame($order, $this->createPlugin()->afterGet($this->createSubject(), $order));
    }

    public function testAfterGetSkipsOrderWithoutRow(): void
    {
        $order = $this->createOrder(7);
        $order->expects($this->never())->method('setExtensionAttributes');
        $this->quoteExtendedRepository->method('getByOrder')->with($order)
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->assertSame($order, $this->createPlugin()->afterGet($this->createSubject(), $order));
    }

    public function testAfterGetSetsValuesOnExistingExtensionAttributes(): void
    {
        $captured = null;
        $extensionAttributes = $this->createExtensionAttributes($captured);
        $order = $this->createOrder(7, $extensionAttributes);
        $order->expects($this->once())->method('setExtensionAttributes')->with($extensionAttributes);

        $this->orderExtensionFactory->expects($this->never())->method('create');
        $this->quoteExtendedRepository->method('getByOrder')->with($order)->willReturn($this->createQuoteExtended([
            'entity_id' => 1,
            'quote_id' => 42,
            'order_id' => 7,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'example_field' => 'foobar',
            'number_field' => 12,
            'empty_field' => null,
        ]));

        $this->assertSame($order, $this->createPlugin()->afterGet($this->createSubject(), $order));

        $this->assertSame([
            ['example_field', 'foobar'],
            ['number_field', '12'],
            ['empty_field', null],
        ], $this->toArray($captured));
    }

    public function testAfterGetCreatesExtensionAttributesWhenMissing(): void
    {
        $captured = null;
        $extensionAttributes = $this->createExtensionAttributes($captured);
        $order = $this->createOrder(7);
        $order->expects($this->once())->method('setExtensionAttributes')->with($extensionAttributes);

        $this->orderExtensionFactory->expects($this->once())->method('create')->willReturn($extensionAttributes);
        $this->quoteExtendedRepository->method('getByOrder')
            ->willReturn($this->createQuoteExtended(['entity_id' => 1, 'example_field' => 'foobar']));

        $this->createPlugin()->afterGet($this->createSubject(), $order);

        $this->assertSame([['example_field', 'foobar']], $this->toArray($captured));
    }

    public function testAfterGetListSkipsWithoutOrderIds(): void
    {
        $order = $this->createOrder(0);
        $order->expects($this->never())->method('setExtensionAttributes');
        $searchResult = $this->createSearchResult([$order]);

        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame($searchResult, $this->createPlugin()->afterGetList($this->createSubject(), $searchResult));
    }

    public function testAfterGetListSkipsEmptyResult(): void
    {
        $searchResult = $this->createSearchResult([]);
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame($searchResult, $this->createPlugin()->afterGetList($this->createSubject(), $searchResult));
    }

    public function testAfterGetListSetsValuesForOrdersWithRows(): void
    {
        $capturedFirst = null;
        $firstExtensionAttributes = $this->createExtensionAttributes($capturedFirst);
        $firstOrder = $this->createOrder(7, $firstExtensionAttributes);
        $firstOrder->expects($this->once())->method('setExtensionAttributes')->with($firstExtensionAttributes);

        $secondOrder = $this->createOrder(8);
        $secondOrder->expects($this->never())->method('setExtensionAttributes');

        $capturedThird = null;
        $thirdExtensionAttributes = $this->createExtensionAttributes($capturedThird);
        $thirdOrder = $this->createOrder(9, $thirdExtensionAttributes);
        $thirdOrder->expects($this->once())->method('setExtensionAttributes')->with($thirdExtensionAttributes);

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addFieldToFilter')
            ->with('order_id', ['in' => [7, 8, 9]])->willReturnSelf();
        $collection->method('getItems')->willReturn([
            $this->createQuoteExtended(['entity_id' => 1, 'order_id' => 7, 'example_field' => 'foo']),
            $this->createQuoteExtended(['entity_id' => 3, 'order_id' => 9, 'example_field' => 'bar']),
        ]);
        $this->collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $searchResult = $this->createSearchResult([$firstOrder, $secondOrder, $thirdOrder]);

        $this->assertSame($searchResult, $this->createPlugin()->afterGetList($this->createSubject(), $searchResult));

        $this->assertSame([['example_field', 'foo']], $this->toArray($capturedFirst));
        $this->assertSame([['example_field', 'bar']], $this->toArray($capturedThird));
    }

    private function createPlugin(): AddQuoteExtendedExtensionAttribute
    {
        return new AddQuoteExtendedExtensionAttribute(
            $this->quoteExtendedRepository,
            $this->collectionFactory,
            $this->orderExtensionFactory,
            $this->quoteExtendedValueFactory
        );
    }

    private function createSubject(): OrderRepositoryInterface
    {
        return $this->createMock(OrderRepositoryInterface::class);
    }

    private function createOrder(int $orderId, ?OrderExtensionInterface $extensionAttributes = null): OrderInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getExtensionAttributes')->willReturn($extensionAttributes);

        return $order;
    }

    private function createSearchResult(array $orders): OrderSearchResultInterface
    {
        $searchResult = $this->createMock(OrderSearchResultInterface::class);
        $searchResult->method('getItems')->willReturn($orders);

        return $searchResult;
    }

    private function createExtensionAttributes(?array &$captured): OrderExtensionInterface
    {
        $mockBuilder = $this->getMockBuilder(OrderExtensionInterface::class);
        if (method_exists(OrderExtensionInterface::class, 'setLokiQuoteExtended')) {
            $mockBuilder->onlyMethods(['setLokiQuoteExtended']);
        } else {
            $mockBuilder->addMethods(['setLokiQuoteExtended']);
        }

        $extensionAttributes = $mockBuilder->getMockForAbstractClass();
        $extensionAttributes->expects($this->once())->method('setLokiQuoteExtended')->willReturnCallback(
            function (array $values) use (&$captured, $extensionAttributes) {
                $captured = $values;
                return $extensionAttributes;
            }
        );

        return $extensionAttributes;
    }

    private function toArray(?array $values): array
    {
        $this->assertIsArray($values);
        $this->assertContainsOnlyInstancesOf(QuoteExtendedValueInterface::class, $values);

        return array_map(
            fn (QuoteExtendedValueInterface $value) => [$value->getCode(), $value->getValue()],
            $values
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
