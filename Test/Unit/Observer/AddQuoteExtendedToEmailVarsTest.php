<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Observer;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Observer\AddQuoteExtendedToEmailVars;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddQuoteExtendedToEmailVarsTest extends TestCase
{
    private QuoteExtendedRepository&MockObject $quoteExtendedRepository;

    protected function setUp(): void
    {
        $this->quoteExtendedRepository = $this->createMock(QuoteExtendedRepository::class);
    }

    public function testAddsQuoteExtendedToTransport(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $quoteExtended = $this->getMockBuilder(QuoteExtended::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteExtended->setData(['example_field' => 'foobar']);
        $this->quoteExtendedRepository->method('getByOrder')->with($order)->willReturn($quoteExtended);

        $transport = new DataObject(['order' => $order]);
        $this->createObserver()->execute($this->createEventObserver($transport));

        $this->assertSame($quoteExtended, $transport->getData('loki_quote_extended'));
    }

    public function testSkipsTransportWithoutQuoteExtended(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->quoteExtendedRepository->method('getByOrder')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $transport = new DataObject(['order' => $order]);
        $this->createObserver()->execute($this->createEventObserver($transport));

        $this->assertFalse($transport->hasData('loki_quote_extended'));
    }

    public function testSkipsTransportWithoutOrder(): void
    {
        $this->quoteExtendedRepository->expects($this->never())->method('getByOrder');

        $transport = new DataObject([]);
        $this->createObserver()->execute($this->createEventObserver($transport));

        $this->assertFalse($transport->hasData('loki_quote_extended'));
    }

    private function createObserver(): AddQuoteExtendedToEmailVars
    {
        return new AddQuoteExtendedToEmailVars($this->quoteExtendedRepository);
    }

    private function createEventObserver(DataObject $transport): Observer
    {
        return new Observer(['event' => new Event(['transportObject' => $transport])]);
    }
}
