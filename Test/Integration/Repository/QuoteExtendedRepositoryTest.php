<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Repository;

use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
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
]
class QuoteExtendedRepositoryTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private DataFixtureStorage $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = DataFixtureStorageManager::getStorage();
    }

    public function testSaveAndLoadByQuoteId(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);

        $this->assertNotEmpty($saved->getId());

        $loaded = $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
        $this->assertSame((int)$saved->getId(), (int)$loaded->getId());
        $this->assertSame($cartId, $loaded->getQuoteId());
        $this->assertNull($loaded->getOrderId());
    }

    public function testGetByQuoteIdThrowsWithoutRow(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->getQuoteExtendedRepository()->getByQuoteId($this->getCartId());
    }

    public function testGetByOrderUsesOrderLink(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        $loaded = $this->getQuoteExtendedRepository()->getByOrder($order);

        $this->assertSame((int)$saved->getId(), (int)$loaded->getId());
        $this->assertSame((int)$order->getEntityId(), $loaded->getOrderId());
    }

    public function testGetByOrderFallsBackToUnlinkedQuoteRow(): void
    {
        $cartId = $this->getCartId();
        $order = $this->placeOrder($cartId);
        $saved = $this->saveQuoteExtended($cartId);

        $loaded = $this->getQuoteExtendedRepository()->getByOrder($order);

        $this->assertSame((int)$saved->getId(), (int)$loaded->getId());
    }

    public function testGetByOrderIgnoresQuoteRowOfOtherOrder(): void
    {
        $cartId = $this->getCartId();
        $order = $this->placeOrder($cartId);
        $this->saveQuoteExtended($cartId, ['order_id' => (int)$order->getEntityId()]);

        $otherOrder = $this->om()->create(Order::class);
        $otherOrder->setEntityId((int)$order->getEntityId() + 1000);
        $otherOrder->setQuoteId($cartId);

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteExtendedRepository()->getByOrder($otherOrder);
    }

    public function testGetOrCreateByQuoteIdDetachesRowOfPlacedOrder(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        $new = $this->getQuoteExtendedRepository()->getOrCreateByQuoteId($cartId);
        $this->assertNull($new->getId());
        $this->assertNull($new->getOrderId());
        $this->assertSame($cartId, $new->getQuoteId());

        $old = $this->loadQuoteExtended((int)$saved->getId());
        $this->assertNull($old->getQuoteId());
        $this->assertSame((int)$order->getEntityId(), $old->getOrderId());

        $this->getQuoteExtendedRepository()->save($new);
        $this->assertNotSame((int)$saved->getId(), (int)$new->getId());
    }

    public function testDeletingQuoteKeepsRowOfOrder(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        $quote = $this->om()->create(Quote::class);
        $quoteResource = $this->om()->get(QuoteResource::class);
        $quoteResource->load($quote, $cartId);
        $quoteResource->delete($quote);

        $row = $this->loadQuoteExtended((int)$saved->getId());
        $this->assertNotEmpty($row->getId());
        $this->assertNull($row->getQuoteId());
        $this->assertSame((int)$order->getEntityId(), $row->getOrderId());
    }

    public function testDeletingOrderDeletesRow(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class)->delete($order);

        $this->assertEmpty($this->loadQuoteExtended((int)$saved->getId())->getId());
    }

    private function getCartId(): int
    {
        return (int)$this->fixtures->get('cart')->getId();
    }
}
