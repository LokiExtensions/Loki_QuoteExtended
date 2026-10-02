<?php
<<<<<<< HEAD
=======

>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
declare(strict_types=1);

namespace Loki\QuoteExtended\Repository;

use Exception;
use Loki\QuoteExtended\Model\QuoteExtended;
<<<<<<< HEAD
use Loki\QuoteExtended\Model\QuoteExtendedFactory as ModelFactory;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as ResourceModel;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class QuoteExtendedRepository
{
    public function __construct(
        private readonly ModelFactory $modelFactory,
        private readonly ResourceModel $resourceModel,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @throws NoSuchEntityException
     */
    public function get(int $id): QuoteExtended
    {
        $model = $this->modelFactory->create();
        $this->resourceModel->load($model, $id);
        if (!$model->getId()) {
            throw new NoSuchEntityException(__('No quote extension found with ID "%1"', $id));
        }

        return $model;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getByQuoteId(int $quoteId): QuoteExtended
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(QuoteExtended::FIELD_QUOTE_ID, ['eq' => $quoteId]);
        $collection->setPageSize(1);

        $model = $collection->getFirstItem();
        if (!$model instanceof QuoteExtended || !$model->getId()) {
            throw new NoSuchEntityException(__('No quote extension found for quote ID "%1"', $quoteId));
        }

        return $model;
=======
use Loki\QuoteExtended\Model\QuoteExtendedFactory;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

class QuoteExtendedRepository
{
    public const RESERVED_COLUMNS = ['entity_id', 'quote_id', 'order_id', 'created_at', 'updated_at'];

    public function __construct(
        private readonly QuoteExtendedResource $resource,
        private readonly QuoteExtendedFactory $quoteExtendedFactory
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
>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
    }

    public function getOrCreateByQuoteId(int $quoteId): QuoteExtended
    {
        try {
<<<<<<< HEAD
            return $this->getByQuoteId($quoteId);
        } catch (NoSuchEntityException) {
            return $this->create($quoteId);
        }
    }

    public function create(int $quoteId): QuoteExtended
    {
        $model = $this->modelFactory->create();
        $model->setQuoteId($quoteId);

        return $model;
    }

    /**
     * @throws CouldNotSaveException
     */
    public function save(QuoteExtended $model): QuoteExtended
    {
        try {
            $this->resourceModel->save($model);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }

        return $model;
    }

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(QuoteExtended $model): void
    {
        try {
            $this->resourceModel->delete($model);
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__($e->getMessage()), $e);
        }
=======
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
>>>>>>> 9959069 (feat: add loki_quote_extended table with model and repository)
    }
}
