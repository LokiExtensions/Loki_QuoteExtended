<?php
declare(strict_types=1);

namespace Loki\QuoteExtended\Repository;

use Exception;
use Loki\QuoteExtended\Model\QuoteExtended;
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
    }

    public function getOrCreateByQuoteId(int $quoteId): QuoteExtended
    {
        try {
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
    }
}
