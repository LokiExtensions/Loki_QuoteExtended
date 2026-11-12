<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Plugin\Order;

use Loki\QuoteExtended\Api\Data\QuoteExtendedValueInterfaceFactory;
use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended\CollectionFactory;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class AddQuoteExtendedExtensionAttribute
{
    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly OrderExtensionFactory $orderExtensionFactory,
        private readonly QuoteExtendedValueInterfaceFactory $quoteExtendedValueFactory
    ) {
    }

    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        if ((int)$order->getEntityId() < 1) {
            return $order;
        }

        try {
            $quoteExtended = $this->quoteExtendedRepository->getByOrder($order);
        } catch (NoSuchEntityException) {
            return $order;
        }

        $this->setExtensionAttribute($order, $quoteExtended);

        return $order;
    }

    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $searchResult
    ): OrderSearchResultInterface {
        $orderIds = [];
        foreach ($searchResult->getItems() as $order) {
            $orderId = (int)$order->getEntityId();
            if ($orderId > 0) {
                $orderIds[] = $orderId;
            }
        }

        if ($orderIds === []) {
            return $searchResult;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', ['in' => $orderIds]);

        $rows = [];
        foreach ($collection->getItems() as $quoteExtended) {
            if ($quoteExtended instanceof QuoteExtended) {
                $rows[(int)$quoteExtended->getOrderId()] = $quoteExtended;
            }
        }

        foreach ($searchResult->getItems() as $order) {
            $quoteExtended = $rows[(int)$order->getEntityId()] ?? null;
            if ($quoteExtended !== null) {
                $this->setExtensionAttribute($order, $quoteExtended);
            }
        }

        return $searchResult;
    }

    private function setExtensionAttribute(OrderInterface $order, QuoteExtended $quoteExtended): void
    {
        $extensionAttributes = $order->getExtensionAttributes() ?? $this->orderExtensionFactory->create();
        $extensionAttributes->setLokiQuoteExtended($this->createValues($quoteExtended));
        $order->setExtensionAttributes($extensionAttributes);
    }

    private function createValues(QuoteExtended $quoteExtended): array
    {
        $values = [];
        foreach ($quoteExtended->getValues() as $code => $value) {
            $values[] = $this->quoteExtendedValueFactory->create([
                'code' => (string)$code,
                'value' => $value !== null ? (string)$value : null,
            ]);
        }

        return $values;
    }
}
