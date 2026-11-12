<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Repository;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\QuoteExtendedFactory;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class QuoteExtendedRepositoryTest extends TestCase
{
    private array $rows = [];
    private array $savedRows = [];
    private array $deletedRows = [];
    private array $upserts = [];
    private array $columns = ['example_field' => 10, 'flag_field' => null, 'number_field' => null];

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
        $this->assertCount(0, $this->savedRows);
    }

    public function testGetOrCreateByQuoteIdReturnsUnlinkedRow(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_id' => 42, 'order_id' => null, 'example_field' => 'foobar'],
        ];

        $quoteExtended = $this->createRepository()->getOrCreateByQuoteId(42);

        $this->assertSame(2, (int)$quoteExtended->getData('entity_id'));
        $this->assertSame('foobar', $quoteExtended->getData('example_field'));
        $this->assertCount(0, $this->savedRows);
    }

    public function testGetByQuoteIdDoesNotLoadInvalidId(): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->expects($this->never())->method('load');

        $factory = $this->createMock(QuoteExtendedFactory::class);
        $factory->method('create')->willReturn($this->createQuoteExtended());

        $this->expectException(NoSuchEntityException::class);

        (new QuoteExtendedRepository($resource, $factory, $this->createMock(LoggerInterface::class)))->getByQuoteId(0);
    }

    public function testGetByOrderIdDoesNotLoadInvalidId(): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->expects($this->never())->method('load');

        $factory = $this->createMock(QuoteExtendedFactory::class);
        $factory->method('create')->willReturn($this->createQuoteExtended());

        $this->expectException(NoSuchEntityException::class);

        (new QuoteExtendedRepository($resource, $factory, $this->createMock(LoggerInterface::class)))->getByOrderId(0);
    }

    public function testGetByOrderRethrowsWithoutQuoteId(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_id' => 42, 'order_id' => null],
        ];

        $this->expectException(NoSuchEntityException::class);

        $this->createRepository()->getByOrder($this->createOrder(7, 0));
    }

    public function testDetachFromQuoteStripsReservedColumns(): void
    {
        $original = $this->createQuoteExtended();
        $original->setData([
            'entity_id' => 2,
            'quote_id' => 42,
            'order_id' => 8,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'example_field' => 'foobar',
        ]);

        $copy = $this->createRepository()->detachFromQuote($original);

        $this->assertSame([
            'example_field' => 'foobar',
            'quote_id' => 42,
        ], $copy->getData());
        $this->assertNull($original->getQuoteId());
        $this->assertSame(8, $original->getOrderId());
        $this->assertCount(1, $this->savedRows);
        $this->assertSame(2, (int)$this->savedRows[0]['entity_id']);
        $this->assertNull($this->savedRows[0]['quote_id']);
    }

    public function testSaveWrapsResourceException(): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->method('save')->willThrowException(new \RuntimeException('Database is gone'));

        $repository = new QuoteExtendedRepository(
            $resource,
            $this->createMock(QuoteExtendedFactory::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Database is gone');

        $repository->save($this->createQuoteExtended());
    }

    public function testDeleteDeletesRow(): void
    {
        $quoteExtended = $this->createQuoteExtended();
        $quoteExtended->setData(['entity_id' => 2, 'quote_id' => 42]);

        $this->createRepository()->delete($quoteExtended);

        $this->assertSame([['entity_id' => 2, 'quote_id' => 42]], $this->deletedRows);
    }

    public function testDeleteWrapsResourceException(): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->method('delete')->willThrowException(new \RuntimeException('Database is gone'));

        $repository = new QuoteExtendedRepository(
            $resource,
            $this->createMock(QuoteExtendedFactory::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Database is gone');

        $repository->delete($this->createQuoteExtended());
    }

    public function testSaveValueUpsertsValueWithoutRow(): void
    {
        $this->createRepository()->saveValue(42, 'example_field', 'foobar');

        $this->assertSame([[42, 'example_field', 'foobar']], $this->upserts);
        $this->assertCount(0, $this->savedRows);
    }

    public function testSaveValueUpsertsValueForUnlinkedRow(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_id' => 42, 'order_id' => null, 'example_field' => 'old'],
        ];

        $this->createRepository()->saveValue(42, 'example_field', 'new');

        $this->assertSame([[42, 'example_field', 'new']], $this->upserts);
        $this->assertCount(0, $this->savedRows);
    }

    public function testSaveValueTruncatesMultibyteString(): void
    {
        $this->createRepository()->saveValue(42, 'example_field', 'äöüßéèêëïîx');

        $this->assertSame([[42, 'example_field', 'äöüßéèêëïî']], $this->upserts);
    }

    public function testSaveValueKeepsStringWithinLength(): void
    {
        $this->createRepository()->saveValue(42, 'example_field', 'äöüßéèêëïî');

        $this->assertSame([[42, 'example_field', 'äöüßéèêëïî']], $this->upserts);
    }

    public function testSaveValueDoesNotTruncateWithoutColumnLength(): void
    {
        $value = str_repeat('a', 300);

        $this->createRepository()->saveValue(42, 'number_field', $value);

        $this->assertSame([[42, 'number_field', $value]], $this->upserts);
    }

    public function testSaveValueConvertsBoolToInt(): void
    {
        $repository = $this->createRepository();
        $repository->saveValue(42, 'flag_field', true);
        $repository->saveValue(42, 'flag_field', false);

        $this->assertSame([[42, 'flag_field', 1], [42, 'flag_field', 0]], $this->upserts);
    }

    public function testSaveValueKeepsNumericValue(): void
    {
        $repository = $this->createRepository();
        $repository->saveValue(42, 'number_field', 12345678901);
        $repository->saveValue(42, 'number_field', 1.5);

        $this->assertSame([[42, 'number_field', 12345678901], [42, 'number_field', 1.5]], $this->upserts);
    }

    /**
     * @dataProvider nonScalarValueProvider
     */
    public function testSaveValueConvertsNonScalarToNull(mixed $value): void
    {
        $this->createRepository()->saveValue(42, 'example_field', $value);

        $this->assertSame([[42, 'example_field', null]], $this->upserts);
    }

    public static function nonScalarValueProvider(): array
    {
        return [
            'null' => [null],
            'array' => [['foo' => 'bar']],
            'object' => [new \stdClass()],
        ];
    }

    public function testSaveValueLogsUnknownColumn(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains('Unknown column'),
            ['column' => 'unknown_field']
        );

        $this->createRepository($logger)->saveValue(42, 'unknown_field', 'foobar');

        $this->assertCount(0, $this->upserts);
        $this->assertCount(0, $this->savedRows);
    }

    /**
     * @dataProvider reservedColumnProvider
     */
    public function testSaveValueIgnoresReservedColumn(string $column): void
    {
        $this->columns[$column] = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $this->createRepository($logger)->saveValue(42, $column, 7);

        $this->assertCount(0, $this->upserts);
        $this->assertCount(0, $this->savedRows);
    }

    public static function reservedColumnProvider(): array
    {
        return array_combine(
            QuoteExtendedRepository::RESERVED_COLUMNS,
            array_map(fn (string $column) => [$column], QuoteExtendedRepository::RESERVED_COLUMNS)
        );
    }

    /**
     * @dataProvider invalidQuoteIdProvider
     */
    public function testSaveValueIgnoresInvalidQuoteId(int $quoteId): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->expects($this->never())->method('hasColumn');
        $resource->expects($this->never())->method('load');
        $resource->expects($this->never())->method('upsertValue');
        $resource->expects($this->never())->method('save');

        (new QuoteExtendedRepository(
            $resource,
            $this->createMock(QuoteExtendedFactory::class),
            $this->createMock(LoggerInterface::class)
        ))->saveValue($quoteId, 'example_field', 'foobar');
    }

    public static function invalidQuoteIdProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function testSaveValueDetachesRowLinkedToOrder(): void
    {
        $this->rows = [
            ['entity_id' => 2, 'quote_id' => 42, 'order_id' => 8, 'example_field' => 'old', 'flag_field' => 1,
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];

        $this->createRepository()->saveValue(42, 'example_field', 'new value that is too long');

        $this->assertCount(0, $this->upserts);
        $this->assertCount(2, $this->savedRows);
        $this->assertSame(2, (int)$this->savedRows[0]['entity_id']);
        $this->assertNull($this->savedRows[0]['quote_id']);
        $this->assertSame(8, (int)$this->savedRows[0]['order_id']);
        $this->assertSame('old', $this->savedRows[0]['example_field']);
        $this->assertSame([
            'example_field' => 'new value ',
            'flag_field' => 1,
            'quote_id' => 42,
        ], $this->savedRows[1]);
    }

    public function testSaveValueWrapsUpsertException(): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->method('hasColumn')->willReturn(true);
        $resource->method('getColumnLength')->willReturn(null);
        $resource->method('upsertValue')->willThrowException(new \RuntimeException('Database is gone'));

        $factory = $this->createMock(QuoteExtendedFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->createQuoteExtended());

        $repository = new QuoteExtendedRepository($resource, $factory, $this->createMock(LoggerInterface::class));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Database is gone');

        $repository->saveValue(42, 'example_field', 'foobar');
    }

    public function testSaveValueWrapsSaveExceptionWhenDetaching(): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->method('hasColumn')->willReturn(true);
        $resource->method('getColumnLength')->willReturn(null);
        $resource->method('load')->willReturnCallback(
            function (QuoteExtended $model) use ($resource) {
                $model->setData(['entity_id' => 2, 'quote_id' => 42, 'order_id' => 8]);
                return $resource;
            }
        );
        $resource->method('save')->willThrowException(new \RuntimeException('Database is gone'));
        $resource->expects($this->never())->method('upsertValue');

        $factory = $this->createMock(QuoteExtendedFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->createQuoteExtended());

        $repository = new QuoteExtendedRepository($resource, $factory, $this->createMock(LoggerInterface::class));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Database is gone');

        $repository->saveValue(42, 'example_field', 'foobar');
    }

    private function createQuoteExtended(): QuoteExtended
    {
        return $this->getMockBuilder(QuoteExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function createRepository(?LoggerInterface $logger = null): QuoteExtendedRepository
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

        $resource->method('delete')->willReturnCallback(
            function (QuoteExtended $model) use ($resource) {
                $this->deletedRows[] = $model->getData();
                return $resource;
            }
        );

        $resource->method('hasColumn')->willReturnCallback(
            fn (string $column) => array_key_exists($column, $this->columns)
        );

        $resource->method('getColumnLength')->willReturnCallback(
            fn (string $column) => $this->columns[$column] ?? null
        );

        $resource->method('upsertValue')->willReturnCallback(
            function (int $quoteId, string $column, mixed $value) {
                $this->upserts[] = [$quoteId, $column, $value];
            }
        );

        $factory = $this->createMock(QuoteExtendedFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->createQuoteExtended());

        return new QuoteExtendedRepository(
            $resource,
            $factory,
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    private function createOrder(int $orderId, int $quoteId): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getQuoteId')->willReturn($quoteId);

        return $order;
    }
}
