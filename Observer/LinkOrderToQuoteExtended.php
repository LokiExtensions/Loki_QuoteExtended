<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Observer;

use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class LinkOrderToQuoteExtended implements ObserverInterface
{
    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quoteId = (int)$observer->getEvent()->getQuote()?->getId();
        $orderId = (int)$observer->getEvent()->getOrder()?->getEntityId();
        if ($quoteId < 1 || $orderId < 1) {
            return;
        }

        try {
            $quoteExtended = $this->quoteExtendedRepository->getByQuoteId($quoteId);
        } catch (NoSuchEntityException) {
            return;
        }

        try {
            $linkedOrderId = $quoteExtended->getOrderId();
            if ($linkedOrderId === $orderId) {
                return;
            }

            if ($linkedOrderId !== null) {
                $quoteExtended = $this->quoteExtendedRepository->detachFromQuote($quoteExtended);
            }

            $quoteExtended->setOrderId($orderId);
            $this->quoteExtendedRepository->save($quoteExtended);
        } catch (CouldNotSaveException $exception) {
            $this->logger->error(
                'Loki_QuoteExtended: Could not link order to extended quote data',
                ['quote_id' => $quoteId, 'order_id' => $orderId, 'exception' => $exception->getMessage()]
            );
        }
    }
}
