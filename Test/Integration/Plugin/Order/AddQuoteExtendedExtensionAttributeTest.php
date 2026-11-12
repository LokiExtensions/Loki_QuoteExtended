<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Plugin\Order;

use Loki\QuoteExtended\Api\Data\QuoteExtendedValueInterface;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
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
    DataFixture(GuestCartFixture::class, as: 'cart2'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart2.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart2.id$']),
]
class AddQuoteExtendedExtensionAttributeTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private const COLUMN_A = 'loki_extension_attribute_test_a';
    private const COLUMN_B = 'loki_extension_attribute_test_b';

    public static function setUpBeforeClass(): void
    {
        self::addQuoteExtendedTestColumn(self::COLUMN_A);
        self::addQuoteExtendedTestColumn(self::COLUMN_B);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropQuoteExtendedTestColumn(self::COLUMN_A);
        self::dropQuoteExtendedTestColumn(self::COLUMN_B);
    }

    public function testGetAddsExtensionAttribute(): void
    {
        $cartId = $this->getCartId('cart');
        $this->saveQuoteExtended($cartId, [self::COLUMN_A => 'foo', self::COLUMN_B => 'bar']);
        $order = $this->placeOrder($cartId);

        $loaded = $this->om()->create(OrderRepositoryInterface::class)->get((int)$order->getEntityId());

        $values = $this->getValues($loaded);
        $this->assertSame('foo', $values[self::COLUMN_A] ?? null);
        $this->assertSame('bar', $values[self::COLUMN_B] ?? null);
        $this->assertArrayNotHasKey('entity_id', $values);
        $this->assertArrayNotHasKey('quote_id', $values);
        $this->assertArrayNotHasKey('order_id', $values);
    }

    public function testGetWithoutRowHasNoExtensionAttribute(): void
    {
        $order = $this->placeOrder($this->getCartId('cart'));

        $loaded = $this->om()->create(OrderRepositoryInterface::class)->get((int)$order->getEntityId());

        $this->assertNull($loaded->getExtensionAttributes()?->getLokiQuoteExtended());
    }

    public function testGetListAddsExtensionAttributePerOrder(): void
    {
        $cartId = $this->getCartId('cart');
        $this->saveQuoteExtended($cartId, [self::COLUMN_A => 'first']);
        $firstOrder = $this->placeOrder($cartId);

        $this->restoreQuote($cartId);
        $this->saveQuoteExtended($cartId, [self::COLUMN_A => 'second']);
        $secondOrder = $this->placeOrder($cartId);

        $thirdOrder = $this->placeOrder($this->getCartId('cart2'));

        $searchCriteria = $this->om()->create(SearchCriteriaBuilder::class)
            ->addFilter('entity_id', [
                (int)$firstOrder->getEntityId(),
                (int)$secondOrder->getEntityId(),
                (int)$thirdOrder->getEntityId(),
            ], 'in')
            ->create();
        $orders = $this->om()->create(OrderRepositoryInterface::class)->getList($searchCriteria)->getItems();

        $this->assertCount(3, $orders);
        $ordersById = [];
        foreach ($orders as $order) {
            $ordersById[(int)$order->getEntityId()] = $order;
        }

        $this->assertSame('first', $this->getValues($ordersById[(int)$firstOrder->getEntityId()])[self::COLUMN_A] ?? null);
        $this->assertSame('second', $this->getValues($ordersById[(int)$secondOrder->getEntityId()])[self::COLUMN_A] ?? null);
        $this->assertNull($ordersById[(int)$thirdOrder->getEntityId()]->getExtensionAttributes()?->getLokiQuoteExtended());
    }

    private function getValues(OrderInterface $order): array
    {
        $items = $order->getExtensionAttributes()?->getLokiQuoteExtended();
        $this->assertIsArray($items);

        $values = [];
        foreach ($items as $item) {
            $this->assertInstanceOf(QuoteExtendedValueInterface::class, $item);
            $values[$item->getCode()] = $item->getValue();
        }

        return $values;
    }

    private function getCartId(string $name): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get($name)->getId();
    }
}
