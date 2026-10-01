<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Observer;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Observer\LinkOrderToQuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
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

        $this->createObserver()->execute($this->createEventObserver(42, 7));
    }

    public function testDoesNothingWithoutOrderId(): void
    {
        $this->quoteExtendedRepository->expects($this->never())->method('getByQuoteId');

        $this->createObserver()->execute($this->createEventObserver(42, 0));
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

    private function createEventObserver(int $quoteId, int $orderId): Observer
    {
        $event = new Event([
            'quote' => new DataObject(['id' => $quoteId]),
            'order' => new DataObject(['entity_id' => $orderId]),
        ]);

        return new Observer(['event' => $event]);
    }
}
