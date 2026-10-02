<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Component;

use Loki\Components\Component\Component;
use Loki\Components\Component\ComponentInterface;
use Loki\QuoteExtended\Component\QuoteExtendedField\QuoteExtendedFieldRepository;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use LokiCheckout\Core\Component\Base\Generic\CheckoutContext;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    AppArea('frontend'),
    DataFixture(GuestCartFixture::class, as: 'cart'),
]
class QuoteExtendedFieldRepositoryTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private const COLUMN = 'loki_integration_test_field';

    public static function setUpBeforeClass(): void
    {
        $connection = self::getConnection();
        $table = self::getTable();
        if (false === $connection->tableColumnExists($table, self::COLUMN)) {
            $connection->addColumn($table, self::COLUMN, [
                'type' => Table::TYPE_TEXT,
                'length' => 255,
                'nullable' => true,
                'comment' => 'Temporary integration test column',
            ]);
        }

        $connection->resetDdlCache($table);
    }

    public static function tearDownAfterClass(): void
    {
        $connection = self::getConnection();
        $table = self::getTable();
        $connection->dropColumn($table, self::COLUMN);
        $connection->resetDdlCache($table);
    }

    protected function tearDown(): void
    {
        self::getConnection()->delete(self::getTable(), ['quote_id = ?' => $this->getCartId()]);
    }

    public function testSaveValueStoresColumn(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.' . self::COLUMN);

        $this->assertSame('', $repository->getValue());

        $repository->saveValue('foobar');

        $this->assertSame('foobar', $repository->getValue());
        $row = $this->getQuoteExtendedRepository()->getByQuoteId($this->getCartId());
        $this->assertSame('foobar', $row->getData(self::COLUMN));
    }

    public function testSaveValueOverwritesExistingValue(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.' . self::COLUMN);
        $repository->saveValue('first');
        $repository->saveValue('second');

        $this->assertSame('second', $repository->getValue());
        $this->assertSame(
            1,
            (int)self::getConnection()->fetchOne(
                'SELECT COUNT(*) FROM ' . self::getTable() . ' WHERE quote_id = ?',
                [$this->getCartId()]
            )
        );
    }

    public function testSaveValueIgnoresReservedColumns(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.order_id');
        $repository->saveValue('1');

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteExtendedRepository()->getByQuoteId($this->getCartId());
    }

    private function createRepository(string $componentName): QuoteExtendedFieldRepository
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(CheckoutState::class)->setQuoteId($this->getCartId());

        $component = $objectManager->create(Component::class, [
            'name' => $componentName,
            'context' => $objectManager->get(CheckoutContext::class),
            'repositoryClass' => QuoteExtendedFieldRepository::class,
        ]);
        $this->assertInstanceOf(ComponentInterface::class, $component);

        $repository = $objectManager->create(QuoteExtendedFieldRepository::class);
        $repository->setComponent($component);

        return $repository;
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }

    private static function getConnection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }

    private static function getTable(): string
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)
            ->getTableName(QuoteExtendedResource::TABLE_NAME);
    }
}
