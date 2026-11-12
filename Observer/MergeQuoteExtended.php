<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Observer;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class MergeQuoteExtended implements ObserverInterface
{
    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $targetQuoteId = (int)$observer->getEvent()->getQuote()?->getId();
        $sourceQuoteId = (int)$observer->getEvent()->getSource()?->getId();
        if ($targetQuoteId < 1 || $sourceQuoteId < 1 || $targetQuoteId === $sourceQuoteId) {
            return;
        }

        try {
            $source = $this->quoteExtendedRepository->getByQuoteId($sourceQuoteId);
        } catch (NoSuchEntityException) {
            return;
        }

        if ($source->getOrderId() !== null) {
            return;
        }

        try {
            $this->merge($source, $targetQuoteId);
        } catch (CouldNotSaveException|CouldNotDeleteException $exception) {
            $this->logger->error(
                'Loki_QuoteExtended: Could not merge extended quote data',
                [
                    'source_quote_id' => $sourceQuoteId,
                    'target_quote_id' => $targetQuoteId,
                    'exception' => $exception->getMessage(),
                ]
            );
        }
    }

    private function merge(QuoteExtended $source, int $targetQuoteId): void
    {
        try {
            $target = $this->quoteExtendedRepository->getByQuoteId($targetQuoteId);
        } catch (NoSuchEntityException) {
            $source->setQuoteId($targetQuoteId);
            $this->quoteExtendedRepository->save($source);
            return;
        }

        if ($target->getOrderId() !== null) {
            $target = $this->quoteExtendedRepository->detachFromQuote($target);
        }

        $values = array_diff_key($source->getData(), array_flip(QuoteExtendedRepository::RESERVED_COLUMNS));
        foreach ($values as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $target->setData($column, $value);
        }

        $this->quoteExtendedRepository->save($target);
        $this->quoteExtendedRepository->delete($source);
    }
}
