<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Observer;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Observer\MergeQuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MergeQuoteExtendedTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
    }

    public function testMovesGuestRowToCustomerQuoteWithoutRow(): void
    {
        $source = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 10, 'example_field' => 'foobar']);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturnCallback(
            fn (int $quoteId) => $quoteId === 10 ? $source : throw new NoSuchEntityException(__('Not found'))
        );
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($source);
        $this->quoteExtendedRepository->expects($this->never())->method('delete');
        $this->quoteExtendedRepository->expects($this->never())->method('detachFromQuote');

        $this->createObserver()->execute($this->createEventObserver(20, 10));

        $this->assertSame(20, $source->getQuoteId());
        $this->assertSame('foobar', $source->getData('example_field'));
    }

    public function testCopiesNonEmptyGuestValuesIntoCustomerRow(): void
    {
        $source = $this->createQuoteExtended([
            'entity_id' => 1,
            'quote_id' => 10,
            'order_id' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            'example_field' => 'guest',
            'empty_field' => '',
            'null_field' => null,
            'zero_field' => 0,
        ]);
        $target = $this->createQuoteExtended([
            'entity_id' => 2,
            'quote_id' => 20,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
            'example_field' => 'customer',
            'empty_field' => 'keep',
            'null_field' => 'keep',
            'other_field' => 'customer',
        ]);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturnMap([
            [10, $source],
            [20, $target],
        ]);
        $this->quoteExtendedRepository->expects($this->never())->method('detachFromQuote');
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($target);
        $this->quoteExtendedRepository->expects($this->once())->method('delete')->with($source);

        $this->createObserver()->execute($this->createEventObserver(20, 10));

        $this->assertSame([
            'entity_id' => 2,
            'quote_id' => 20,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
            'example_field' => 'guest',
            'empty_field' => 'keep',
            'null_field' => 'keep',
            'other_field' => 'customer',
            'zero_field' => 0,
        ], $target->getData());
    }

    public function testDetachesCustomerRowLinkedToOrder(): void
    {
        $source = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 10, 'example_field' => 'guest']);
        $target = $this->createQuoteExtended(['entity_id' => 2, 'quote_id' => 20, 'order_id' => 7]);
        $copy = $this->createQuoteExtended(['quote_id' => 20, 'other_field' => 'customer']);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturnMap([
            [10, $source],
            [20, $target],
        ]);
        $this->quoteExtendedRepository->expects($this->once())->method('detachFromQuote')
            ->with($target)->willReturn($copy);
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($copy);
        $this->quoteExtendedRepository->expects($this->once())->method('delete')->with($source);

        $this->createObserver()->execute($this->createEventObserver(20, 10));

        $this->assertSame('guest', $copy->getData('example_field'));
        $this->assertSame('customer', $copy->getData('other_field'));
        $this->assertNull($target->getData('example_field'));
    }

    public function testSkipsGuestRowLinkedToOrder(): void
    {
        $source = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 10, 'order_id' => 7]);

        $this->quoteExtendedRepository->expects($this->once())->method('getByQuoteId')
            ->with(10)->willReturn($source);
        $this->quoteExtendedRepository->expects($this->never())->method('save');
        $this->quoteExtendedRepository->expects($this->never())->method('delete');

        $this->createObserver()->execute($this->createEventObserver(20, 10));

        $this->assertSame(10, $source->getQuoteId());
    }

    public function testDoesNothingWithoutGuestRow(): void
    {
        $this->quoteExtendedRepository->expects($this->once())->method('getByQuoteId')
            ->with(10)->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->never())->method('save');
        $this->quoteExtendedRepository->expects($this->never())->method('delete');

        $this->createObserver()->execute($this->createEventObserver(20, 10));
    }

    /**
     * @dataProvider invalidQuoteIdsProvider
     */
    public function testDoesNothingWithInvalidQuoteIds(int $targetQuoteId, int $sourceQuoteId): void
    {
        $this->quoteExtendedRepository->expects($this->never())->method('getByQuoteId');

        $this->createObserver()->execute($this->createEventObserver($targetQuoteId, $sourceQuoteId));
    }

    public static function invalidQuoteIdsProvider(): array
    {
        return [
            'no target' => [0, 10],
            'no source' => [20, 0],
            'same quote' => [20, 20],
        ];
    }

    public function testDoesNothingWithoutQuoteObjects(): void
    {
        $this->quoteExtendedRepository->expects($this->never())->method('getByQuoteId');

        $this->createObserver()->execute(new Observer(['event' => new Event([])]));
    }

    public function testLogsErrorWhenSaveFails(): void
    {
        $source = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 10]);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturnCallback(
            fn (int $quoteId) => $quoteId === 10 ? $source : throw new NoSuchEntityException(__('Not found'))
        );
        $this->quoteExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Database is gone')));

        $this->createObserverExpectingError('Database is gone')->execute($this->createEventObserver(20, 10));
    }

    public function testLogsErrorWhenDeleteFails(): void
    {
        $source = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 10, 'example_field' => 'guest']);
        $target = $this->createQuoteExtended(['entity_id' => 2, 'quote_id' => 20]);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturnMap([
            [10, $source],
            [20, $target],
        ]);
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($target);
        $this->quoteExtendedRepository->method('delete')
            ->willThrowException(new CouldNotDeleteException(__('Database is gone')));

        $this->createObserverExpectingError('Database is gone')->execute($this->createEventObserver(20, 10));
    }

    public function testLogsErrorWhenDetachFails(): void
    {
        $source = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 10]);
        $target = $this->createQuoteExtended(['entity_id' => 2, 'quote_id' => 20, 'order_id' => 7]);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturnMap([
            [10, $source],
            [20, $target],
        ]);
        $this->quoteExtendedRepository->method('detachFromQuote')
            ->willThrowException(new CouldNotSaveException(__('Database is gone')));
        $this->quoteExtendedRepository->expects($this->never())->method('save');
        $this->quoteExtendedRepository->expects($this->never())->method('delete');

        $this->createObserverExpectingError('Database is gone')->execute($this->createEventObserver(20, 10));
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

    private function createObserver(): MergeQuoteExtended
    {
        return new MergeQuoteExtended(
            $this->quoteExtendedRepository,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createObserverExpectingError(string $message): MergeQuoteExtended
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains('Could not merge'),
            $this->callback(fn (array $context) => $context['source_quote_id'] === 10
                && $context['target_quote_id'] === 20
                && $context['exception'] === $message)
        );

        return new MergeQuoteExtended($this->quoteExtendedRepository, $logger);
    }

    private function createEventObserver(int $targetQuoteId, int $sourceQuoteId): Observer
    {
        $event = new Event([
            'quote' => new DataObject(['id' => $targetQuoteId]),
            'source' => new DataObject(['id' => $sourceQuoteId]),
        ]);

        return new Observer(['event' => $event]);
    }
}
