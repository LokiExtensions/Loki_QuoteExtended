<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Repository;

use Exception;
use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\QuoteExtendedFactory;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class QuoteExtendedRepository
{
    public const RESERVED_COLUMNS = ['entity_id', 'quote_id', 'order_id', 'created_at', 'updated_at'];

    public function __construct(
        private readonly QuoteExtendedResource $resource,
        private readonly QuoteExtendedFactory $quoteExtendedFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getByQuoteId(int $quoteId): QuoteExtended
    {
        return $this->getByField('quote_id', $quoteId);
    }

    public function getByOrderId(int $orderId): QuoteExtended
    {
        return $this->getByField('order_id', $orderId);
    }

    public function getByOrder(OrderInterface $order): QuoteExtended
    {
        try {
            return $this->getByOrderId((int)$order->getEntityId());
        } catch (NoSuchEntityException $exception) {
            $quoteId = (int)$order->getQuoteId();
            if ($quoteId < 1) {
                throw $exception;
            }

            $quoteExtended = $this->getByQuoteId($quoteId);
            $linkedOrderId = $quoteExtended->getOrderId();
            if ($linkedOrderId !== null && $linkedOrderId !== (int)$order->getEntityId()) {
                throw $exception;
            }

            return $quoteExtended;
        }
    }

    public function getOrCreateByQuoteId(int $quoteId): QuoteExtended
    {
        try {
            $quoteExtended = $this->getByQuoteId($quoteId);
        } catch (NoSuchEntityException) {
            $quoteExtended = $this->quoteExtendedFactory->create();
            $quoteExtended->setQuoteId($quoteId);
            return $quoteExtended;
        }

        if ($quoteExtended->getOrderId() !== null) {
            return $this->detachFromQuote($quoteExtended);
        }

        return $quoteExtended;
    }

    public function detachFromQuote(QuoteExtended $quoteExtended): QuoteExtended
    {
        $copy = $this->quoteExtendedFactory->create();
        $copy->setData(array_diff_key($quoteExtended->getData(), array_flip(self::RESERVED_COLUMNS)));
        $copy->setQuoteId($quoteExtended->getQuoteId());

        $quoteExtended->setQuoteId(null);
        $this->save($quoteExtended);

        return $copy;
    }

    public function copyToOrder(QuoteExtended $source, int $quoteId, int $orderId): QuoteExtended
    {
        $copy = $this->quoteExtendedFactory->create();
        $copy->setData($source->getValues());
        $copy->setQuoteId($quoteId);
        $copy->setOrderId($orderId);

        return $this->save($copy);
    }

    public function save(QuoteExtended $quoteExtended): QuoteExtended
    {
        try {
            $this->resource->save($quoteExtended);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the extended quote data: %1', $exception->getMessage()),
                $exception
            );
        }

        return $quoteExtended;
    }

    public function delete(QuoteExtended $quoteExtended): void
    {
        try {
            $this->resource->delete($quoteExtended);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the extended quote data: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    public function saveValue(int $quoteId, string $column, mixed $value): void
    {
        if ($quoteId < 1 || in_array($column, self::RESERVED_COLUMNS, true)) {
            return;
        }

        if (!$this->resource->hasColumn($column)) {
            $this->logger->error('Loki_QuoteExtended: Unknown column', ['column' => $column]);
            return;
        }

        $value = $this->normalizeValue($column, $value);

        try {
            $quoteExtended = $this->getByQuoteId($quoteId);
        } catch (NoSuchEntityException) {
            $quoteExtended = null;
        }

        if ($quoteExtended !== null && $quoteExtended->getOrderId() !== null) {
            $quoteExtended = $this->detachFromQuote($quoteExtended);
            $quoteExtended->setData($column, $value);
            $this->save($quoteExtended);
            return;
        }

        try {
            $this->resource->upsertValue($quoteId, $column, $value);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the extended quote data: %1', $exception->getMessage()),
                $exception
            );
        }
    }

    private function normalizeValue(string $column, mixed $value): mixed
    {
        if (!is_scalar($value)) {
            return null;
        }

        if (is_bool($value)) {
            return (int)$value;
        }

        $length = $this->resource->getColumnLength($column);
        if (is_string($value) && $length !== null && mb_strlen($value) > $length) {
            return mb_substr($value, 0, $length);
        }

        return $value;
    }

    private function getByField(string $field, int $value): QuoteExtended
    {
        $quoteExtended = $this->quoteExtendedFactory->create();
        if ($value > 0) {
            $this->resource->load($quoteExtended, $value, $field);
        }

        if (!$quoteExtended->getData(QuoteExtendedResource::PRIMARY_KEY)) {
            throw new NoSuchEntityException(
                __('No extended quote data found for %1 "%2".', $field, $value)
            );
        }

        return $quoteExtended;
    }
}
