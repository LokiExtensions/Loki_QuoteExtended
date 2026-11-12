<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Trait;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use ReflectionProperty;

trait QuoteExtendedTestTrait
{
    protected function getQuoteExtendedRepository(): QuoteExtendedRepository
    {
        return $this->om()->get(QuoteExtendedRepository::class);
    }

    protected function saveQuoteExtended(int $quoteId, array $data = []): QuoteExtended
    {
        $repository = $this->getQuoteExtendedRepository();
        $quoteExtended = $repository->getOrCreateByQuoteId($quoteId);
        foreach ($data as $key => $value) {
            $quoteExtended->setData($key, $value);
        }

        return $repository->save($quoteExtended);
    }

    protected function loadQuoteExtended(int $entityId): QuoteExtended
    {
        $quoteExtended = $this->om()->create(QuoteExtended::class);
        $this->om()->get(QuoteExtendedResource::class)->load($quoteExtended, $entityId);

        return $quoteExtended;
    }

    protected function placeOrder(int $cartId): OrderInterface
    {
        $paymentMethod = $this->om()->get(PaymentMethodManagementInterface::class)->get($cartId);
        $orderId = (int)$this->om()->get(CartManagementInterface::class)->placeOrder($cartId, $paymentMethod);

        return $this->om()->create(OrderRepositoryInterface::class)->get($orderId);
    }

    protected function restoreQuote(int $cartId): void
    {
        $cartRepository = $this->om()->create(CartRepositoryInterface::class);
        $quote = $cartRepository->get($cartId);
        $quote->setIsActive(true);
        $quote->setReservedOrderId(null);
        $cartRepository->save($quote);
    }

    protected function createInvoice(OrderInterface $order): InvoiceInterface
    {
        $invoiceId = $this->om()->get(InvoiceOrderInterface::class)->execute((int)$order->getEntityId());

        return $this->om()->create(InvoiceRepositoryInterface::class)->get((int)$invoiceId);
    }

    protected static function addQuoteExtendedTestColumn(string $column, int $length = 255): void
    {
        $connection = self::getQuoteExtendedConnection();
        $table = self::getQuoteExtendedTable();
        if (false === $connection->tableColumnExists($table, $column)) {
            $connection->addColumn($table, $column, [
                'type' => Table::TYPE_TEXT,
                'length' => $length,
                'nullable' => true,
                'comment' => 'Temporary integration test column',
            ]);
        }

        self::resetQuoteExtendedColumnCache();
    }

    protected static function dropQuoteExtendedTestColumn(string $column): void
    {
        self::getQuoteExtendedConnection()->dropColumn(self::getQuoteExtendedTable(), $column);
        self::resetQuoteExtendedColumnCache();
    }

    protected static function resetQuoteExtendedColumnCache(): void
    {
        self::getQuoteExtendedConnection()->resetDdlCache(self::getQuoteExtendedTable());
        $resource = Bootstrap::getObjectManager()->get(QuoteExtendedResource::class);
        (new ReflectionProperty(QuoteExtendedResource::class, 'columns'))->setValue($resource, null);
    }

    protected static function getQuoteExtendedConnection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    protected static function getQuoteExtendedTable(): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)
            ->getTableName(QuoteExtendedResource::TABLE_NAME);
    }

    protected function countQuoteExtendedRows(int $quoteId): int
    {
        return (int)self::getQuoteExtendedConnection()->fetchOne(
            'SELECT COUNT(*) FROM ' . self::getQuoteExtendedTable() . ' WHERE quote_id = ?',
            [$quoteId]
        );
    }

    protected function om(): ObjectManagerInterface
    {
        return Bootstrap::getObjectManager();
    }
}
