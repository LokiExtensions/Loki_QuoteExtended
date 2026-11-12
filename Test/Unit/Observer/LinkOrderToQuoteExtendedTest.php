<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Observer;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Observer\LinkOrderToQuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LinkOrderToQuoteExtendedTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
    }

    public function testLinksOrderIdToQuoteExtended(): void
    {
        $quoteExtended = $this->createQuoteExtended(['quote_id' => 42]);

        $this->quoteExtendedRepository->method('getByQuoteId')->with(42)->willReturn($quoteExtended);
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($quoteExtended);

        $this->createObserver()->execute($this->createEventObserver(42, 7));

        $this->assertSame(7, $quoteExtended->getOrderId());
    }

    public function testDetachesRowAlreadyLinkedToOtherOrder(): void
    {
        $quoteExtended = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 42, 'order_id' => 6]);
        $copy = $this->createQuoteExtended(['quote_id' => 42, 'example_field' => 'foobar']);

        $this->quoteExtendedRepository->method('getByQuoteId')->with(42)->willReturn($quoteExtended);
        $this->quoteExtendedRepository->expects($this->once())->method('detachFromQuote')
            ->with($quoteExtended)->willReturn($copy);
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($copy);

        $this->createObserver()->execute($this->createEventObserver(42, 7));

        $this->assertSame(7, $copy->getOrderId());
        $this->assertSame(6, $quoteExtended->getOrderId());
    }

    public function testDoesNothingWithoutQuoteExtended(): void
    {
        $this->quoteExtendedRepository->method('getByQuoteId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->never())->method('save');
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrderId');
        $this->quoteExtendedRepository->expects($this->never())->method('copyToOrder');

        $this->createObserver()->execute($this->createEventObserver(42, 7));
    }

    public function testCopiesRowOfParentOrderWhenQuoteHasNoRow(): void
    {
        $parent = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 41, 'order_id' => 6]);

        $this->quoteExtendedRepository->method('getByQuoteId')->with(42)
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->once())->method('getByOrderId')->with(6)->willReturn($parent);
        $this->quoteExtendedRepository->expects($this->once())->method('copyToOrder')
            ->with($parent, 42, 7)->willReturn($this->createQuoteExtended([]));
        $this->quoteExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver(42, 7, 6));
    }

    public function testDoesNothingWhenParentOrderHasNoRow(): void
    {
        $this->quoteExtendedRepository->method('getByQuoteId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->once())->method('getByOrderId')->with(6)
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->expects($this->never())->method('copyToOrder');
        $this->quoteExtendedRepository->expects($this->never())->method('save');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        (new LinkOrderToQuoteExtended($this->quoteExtendedRepository, $logger))
            ->execute($this->createEventObserver(42, 7, 6));
    }

    public function testDoesNotCopyParentWhenQuoteHasRow(): void
    {
        $quoteExtended = $this->createQuoteExtended(['quote_id' => 42]);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturn($quoteExtended);
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrderId');
        $this->quoteExtendedRepository->expects($this->never())->method('copyToOrder');
        $this->quoteExtendedRepository->expects($this->once())->method('save')->with($quoteExtended);

        $this->createObserver()->execute($this->createEventObserver(42, 7, 6));

        $this->assertSame(7, $quoteExtended->getOrderId());
    }

    public function testLogsErrorWhenCopyFromParentFails(): void
    {
        $this->quoteExtendedRepository->method('getByQuoteId')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->quoteExtendedRepository->method('getByOrderId')
            ->willReturn($this->createQuoteExtended(['entity_id' => 1, 'order_id' => 6]));
        $this->quoteExtendedRepository->method('copyToOrder')
            ->willThrowException(new CouldNotSaveException(__('Database is gone')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains('Could not link order'),
            $this->callback(fn (array $context) => $context['quote_id'] === 42
                && $context['order_id'] === 7
                && $context['exception'] === 'Database is gone')
        );

        (new LinkOrderToQuoteExtended($this->quoteExtendedRepository, $logger))
            ->execute($this->createEventObserver(42, 7, 6));
    }

    public function testDoesNothingWithoutOrderId(): void
    {
        $this->quoteExtendedRepository->expects($this->never())->method('getByQuoteId');

        $this->createObserver()->execute($this->createEventObserver(42, 0));
    }

    public function testDoesNothingWhenAlreadyLinkedToSameOrder(): void
    {
        $quoteExtended = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 42, 'order_id' => 7]);

        $this->quoteExtendedRepository->method('getByQuoteId')->with(42)->willReturn($quoteExtended);
        $this->quoteExtendedRepository->expects($this->never())->method('detachFromQuote');
        $this->quoteExtendedRepository->expects($this->never())->method('save');

        $this->createObserver()->execute($this->createEventObserver(42, 7));
    }

    public function testDoesNothingWithoutQuoteId(): void
    {
        $this->quoteExtendedRepository->expects($this->never())->method('getByQuoteId');

        $this->createObserver()->execute($this->createEventObserver(0, 7));
    }

    public function testDoesNothingWithoutQuoteAndOrderObjects(): void
    {
        $this->quoteExtendedRepository->expects($this->never())->method('getByQuoteId');

        $this->createObserver()->execute(new Observer(['event' => new Event([])]));
    }

    public function testLogsErrorWhenSaveFails(): void
    {
        $quoteExtended = $this->createQuoteExtended(['quote_id' => 42]);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturn($quoteExtended);
        $this->quoteExtendedRepository->method('save')
            ->willThrowException(new CouldNotSaveException(__('Database is gone')));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains('Could not link order'),
            $this->callback(fn (array $context) => $context['quote_id'] === 42
                && $context['order_id'] === 7
                && $context['exception'] === 'Database is gone')
        );

        (new LinkOrderToQuoteExtended($this->quoteExtendedRepository, $logger))
            ->execute($this->createEventObserver(42, 7));
    }

    public function testLogsErrorWhenDetachFails(): void
    {
        $quoteExtended = $this->createQuoteExtended(['entity_id' => 1, 'quote_id' => 42, 'order_id' => 6]);

        $this->quoteExtendedRepository->method('getByQuoteId')->willReturn($quoteExtended);
        $this->quoteExtendedRepository->method('detachFromQuote')
            ->willThrowException(new CouldNotSaveException(__('Database is gone')));
        $this->quoteExtendedRepository->expects($this->never())->method('save');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->stringContains('Could not link order'),
            $this->callback(fn (array $context) => $context['quote_id'] === 42
                && $context['order_id'] === 7
                && $context['exception'] === 'Database is gone')
        );

        (new LinkOrderToQuoteExtended($this->quoteExtendedRepository, $logger))
            ->execute($this->createEventObserver(42, 7));

        $this->assertSame(6, $quoteExtended->getOrderId());
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

    private function createObserver(): LinkOrderToQuoteExtended
    {
        return new LinkOrderToQuoteExtended(
            $this->quoteExtendedRepository,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createEventObserver(int $quoteId, int $orderId, ?int $parentOrderId = null): Observer
    {
        $event = new Event([
            'quote' => new DataObject(['id' => $quoteId]),
            'order' => new DataObject(['entity_id' => $orderId, 'relation_parent_id' => $parentOrderId]),
        ]);

        return new Observer(['event' => $event]);
    }
}
