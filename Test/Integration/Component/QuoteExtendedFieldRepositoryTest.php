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
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(false),
    AppArea('frontend'),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(GuestCartFixture::class, as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
]
class QuoteExtendedFieldRepositoryTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private const COLUMN = 'loki_integration_test_field';

    /** @var OrderInterface[] */
    private array $orders = [];

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
        $this->deleteOrders();
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

    public function testSaveValueStoresNullForNonScalarValue(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.' . self::COLUMN);
        $repository->saveValue('foobar');
        $repository->saveValue(['foo' => 'bar']);

        $this->assertSame('', $repository->getValue());
        $row = $this->getQuoteExtendedRepository()->getByQuoteId($this->getCartId());
        $this->assertNull($row->getData(self::COLUMN));
    }

    public function testFieldNameBlockArgumentIsUsedAsColumn(): void
    {
        $repository = $this->createRepository('loki-checkout.customer.some_other_block', self::COLUMN);
        $repository->saveValue('foobar');

        $row = $this->getQuoteExtendedRepository()->getByQuoteId($this->getCartId());
        $this->assertSame('foobar', $row->getData(self::COLUMN));
        $this->assertSame('foobar', $repository->getValue());
    }

    public function testSaveValueAfterOrderCreatesNewRowAndKeepsOrderRow(): void
    {
        $cartId = $this->getCartId();
        $repository = $this->createRepository('loki-checkout.customer.' . self::COLUMN);
        $repository->saveValue('first');
        $orderRowId = (int)$this->getQuoteExtendedRepository()->getByQuoteId($cartId)->getId();

        $order = $this->placeOrder($cartId);
        $this->orders[] = $order;
        $this->restoreQuote($cartId);

        $repository = $this->createRepository('loki-checkout.customer.' . self::COLUMN);
        $repository->saveValue('second');

        $this->assertSame('second', $repository->getValue());

        $orderRow = $this->loadQuoteExtended($orderRowId);
        $this->assertSame('first', $orderRow->getData(self::COLUMN));
        $this->assertNull($orderRow->getQuoteId());
        $this->assertSame((int)$order->getEntityId(), $orderRow->getOrderId());

        $quoteRow = $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
        $this->assertNotSame($orderRowId, (int)$quoteRow->getId());
        $this->assertSame('second', $quoteRow->getData(self::COLUMN));
        $this->assertNull($quoteRow->getOrderId());
    }

    private function createRepository(string $componentName, string $fieldName = ''): QuoteExtendedFieldRepository
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(CheckoutState::class)->setQuoteId($this->getCartId());

        $component = $objectManager->create(Component::class, [
            'name' => $componentName,
            'context' => $objectManager->get(CheckoutContext::class),
            'repositoryClass' => QuoteExtendedFieldRepository::class,
        ]);
        $this->assertInstanceOf(ComponentInterface::class, $component);

        if ($fieldName !== '') {
            $block = $objectManager->get(LayoutInterface::class)->createBlock(
                Template::class,
                '',
                ['data' => ['field_name' => $fieldName]]
            );
            $component->setBlock($block);
        }

        $repository = $objectManager->create(QuoteExtendedFieldRepository::class);
        $repository->setComponent($component);

        return $repository;
    }

    private function deleteOrders(): void
    {
        if (empty($this->orders)) {
            return;
        }

        $registry = Bootstrap::getObjectManager()->get(Registry::class);
        $registry->register('isSecureArea', true, true);
        try {
            $orderRepository = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);
            foreach ($this->orders as $order) {
                $orderRepository->delete($order);
            }
        } finally {
            $registry->unregister('isSecureArea');
            $this->orders = [];
        }
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
