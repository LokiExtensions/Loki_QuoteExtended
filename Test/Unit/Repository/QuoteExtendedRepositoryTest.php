<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Repository;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\QuoteExtendedFactory;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;

class QuoteExtendedRepositoryTest extends TestCase
{
    private array $rows = [];
    private array $savedRows = [];

    public function testGetByOrderPrefersOrderId(): void
    {
        $this->rows = [
            ['entity_id' => 1, 'quote_id' => 42, 'order_id' => 7],
        ];

        $quoteExtended = $this->createRepository()->getByOrder($this->createOrder(7, 42));

        $this->assertSame(1, (int)$quoteExtended->getData('entity_id'));
    }

    public function testGetByOrderFallsBackToQuoteId(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_id' => 42, 'order_id' => null],
        ];

        $quoteExtended = $this->createRepository()->getByOrder($this->createOrder(7, 42));

        $this->assertSame(2, (int)$quoteExtended->getData('entity_id'));
    }

    public function testGetByOrderIgnoresQuoteRowOfOtherOrder(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_id' => 42, 'order_id' => 8],
        ];

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository()->getByOrder($this->createOrder(7, 42));
    }

    public function testGetOrCreateByQuoteIdDetachesRowLinkedToOrder(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_id' => 42, 'order_id' => 8, 'example_field' => 'foobar',
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];

        $quoteExtended = $this->createRepository()->getOrCreateByQuoteId(42);

        $this->assertNull($quoteExtended->getData('entity_id'));
        $this->assertNull($quoteExtended->getOrderId());
        $this->assertNull($quoteExtended->getData('created_at'));
        $this->assertSame(42, $quoteExtended->getQuoteId());
        $this->assertSame('foobar', $quoteExtended->getData('example_field'));
        $this->assertCount(1, $this->savedRows);
        $this->assertNull($this->savedRows[0]['quote_id']);
        $this->assertSame(8, (int)$this->savedRows[0]['order_id']);
    }

    public function testGetByOrderThrowsWithoutRow(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->createRepository()->getByOrder($this->createOrder(7, 42));
    }

    public function testGetOrCreateByQuoteIdCreatesNewModel(): void
    {
        $quoteExtended = $this->createRepository()->getOrCreateByQuoteId(42);

        $this->assertNull($quoteExtended->getData('entity_id'));
        $this->assertSame(42, $quoteExtended->getQuoteId());
    }

    private function createRepository(): QuoteExtendedRepository
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->method('load')->willReturnCallback(
            function (QuoteExtended $model, $value, $field) use ($resource) {
                foreach ($this->rows as $row) {
                    if ((int)($row[$field] ?? 0) === (int)$value) {
                        $model->setData($row);
                    }
                }

                return $resource;
            }
        );

        $resource->method('save')->willReturnCallback(
            function (QuoteExtended $model) use ($resource) {
                $this->savedRows[] = $model->getData();
                return $resource;
            }
        );

        $factory = $this->createMock(QuoteExtendedFactory::class);
        $factory->method('create')->willReturnCallback(
            fn () => $this->getMockBuilder(QuoteExtended::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );

        return new QuoteExtendedRepository($resource, $factory);
    }

    private function createOrder(int $orderId, int $quoteId): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getQuoteId')->willReturn($quoteId);

        return $order;
    }
}
