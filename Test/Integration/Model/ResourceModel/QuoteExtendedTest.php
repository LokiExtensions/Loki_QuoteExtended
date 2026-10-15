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
