<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Model\ResourceModel;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended\Collection;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(GuestCartFixture::class, as: 'cart'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
    DataFixture(GuestCartFixture::class, as: 'other_cart'),
]
class QuoteExtendedTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private const COLUMN = 'loki_resource_test_field';
    private const OTHER_COLUMN = 'loki_resource_test_other';

    public static function setUpBeforeClass(): void
    {
        self::addQuoteExtendedTestColumn(self::COLUMN, 32);
        self::addQuoteExtendedTestColumn(self::OTHER_COLUMN);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropQuoteExtendedTestColumn(self::COLUMN);
        self::dropQuoteExtendedTestColumn(self::OTHER_COLUMN);
    }

    public function testDeleteOrphanedRowsKeepsRowsLinkedToQuoteOrOrder(): void
    {
        $cartId = $this->getCartId('cart');
        $orderRowId = (int)$this->saveQuoteExtended($cartId)->getId();
        $order = $this->placeOrder($cartId);
        $this->getQuoteExtendedRepository()->detachFromQuote($this->loadQuoteExtended($orderRowId));
        $quoteRowId = (int)$this->saveQuoteExtended($this->getCartId('other_cart'))->getId();

        $resource = Bootstrap::getObjectManager()->get(QuoteExtendedResource::class);
        $resource->deleteOrphanedRows();

        $this->saveOrphan();
        $this->saveOrphan();

        $this->assertSame(2, $resource->deleteOrphanedRows());
        $this->assertSame(0, $resource->deleteOrphanedRows());

        $quoteRow = $this->loadQuoteExtended($quoteRowId);
        $this->assertNotEmpty($quoteRow->getId());
        $this->assertNull($quoteRow->getOrderId());

        $orderRow = $this->loadQuoteExtended($orderRowId);
        $this->assertNotEmpty($orderRow->getId());
        $this->assertNull($orderRow->getQuoteId());
        $this->assertSame((int)$order->getEntityId(), $orderRow->getOrderId());
    }

    public function testCollectionFiltersOnQuoteId(): void
    {
        $cartId = $this->getCartId('cart');
        $saved = $this->saveQuoteExtended($cartId);
        $this->saveQuoteExtended($this->getCartId('other_cart'));

        $collection = $this->createCollection()->addFieldToFilter('quote_id', $cartId);

        $this->assertCount(1, $collection);
        $item = $collection->getFirstItem();
        $this->assertInstanceOf(QuoteExtended::class, $item);
        $this->assertSame((int)$saved->getId(), (int)$item->getId());
        $this->assertSame($cartId, $item->getQuoteId());
    }

    public function testUpsertValueInsertsRow(): void
    {
        $cartId = $this->getCartId('cart');

        $this->getResource()->upsertValue($cartId, self::COLUMN, 'foobar');

        $this->assertSame(1, $this->countQuoteExtendedRows($cartId));
        $row = $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
        $this->assertSame('foobar', $row->getData(self::COLUMN));
        $this->assertNull($row->getOrderId());
    }

    public function testUpsertValueUpdatesOnlyGivenColumnOfExistingRow(): void
    {
        $cartId = $this->getCartId('cart');
        $saved = $this->saveQuoteExtended($cartId, [self::COLUMN => 'first', self::OTHER_COLUMN => 'other']);
        $otherRow = $this->saveQuoteExtended($this->getCartId('other_cart'), [self::COLUMN => 'untouched']);

        $this->getResource()->upsertValue($cartId, self::COLUMN, 'second');
        $this->getResource()->upsertValue($cartId, self::COLUMN, null);
        $this->getResource()->upsertValue($cartId, self::COLUMN, 'third');

        $this->assertSame(1, $this->countQuoteExtendedRows($cartId));
        $row = $this->loadQuoteExtended((int)$saved->getId());
        $this->assertSame($cartId, $row->getQuoteId());
        $this->assertSame('third', $row->getData(self::COLUMN));
        $this->assertSame('other', $row->getData(self::OTHER_COLUMN));
        $this->assertSame('untouched', $this->loadQuoteExtended((int)$otherRow->getId())->getData(self::COLUMN));
    }

    public function testHasColumn(): void
    {
        $resource = $this->getResource();

        $this->assertTrue($resource->hasColumn('quote_id'));
        $this->assertTrue($resource->hasColumn(self::COLUMN));
        $this->assertFalse($resource->hasColumn('loki_unknown_column'));
    }

    public function testGetColumnLength(): void
    {
        $resource = $this->getResource();

        $this->assertSame(32, $resource->getColumnLength(self::COLUMN));
        $this->assertSame(255, $resource->getColumnLength(self::OTHER_COLUMN));
        $this->assertNull($resource->getColumnLength('quote_id'));
        $this->assertNull($resource->getColumnLength('created_at'));
        $this->assertNull($resource->getColumnLength('loki_unknown_column'));
    }

    private function getResource(): QuoteExtendedResource
    {
        return Bootstrap::getObjectManager()->get(QuoteExtendedResource::class);
    }

    private function saveOrphan(): void
    {
        $orphan = Bootstrap::getObjectManager()->create(QuoteExtended::class);
        $orphan->setQuoteId(null);
        $orphan->setOrderId(null);
        $this->getQuoteExtendedRepository()->save($orphan);
    }

    private function createCollection(): Collection
    {
        return Bootstrap::getObjectManager()->create(Collection::class);
    }

    private function getCartId(string $name): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get($name)->getId();
    }
}
