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
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
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
]
class QuoteExtendedRepositorySaveValueTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private const COLUMN_A = 'loki_save_value_test_field_a';
    private const COLUMN_B = 'loki_save_value_test_field_b';
    private const SHORT_COLUMN = 'loki_save_value_test_short';

    public static function setUpBeforeClass(): void
    {
        self::addQuoteExtendedTestColumn(self::COLUMN_A);
        self::addQuoteExtendedTestColumn(self::COLUMN_B);
        self::addQuoteExtendedTestColumn(self::SHORT_COLUMN, 10);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropQuoteExtendedTestColumn(self::COLUMN_A);
        self::dropQuoteExtendedTestColumn(self::COLUMN_B);
        self::dropQuoteExtendedTestColumn(self::SHORT_COLUMN);
    }

    public function testSaveValueCreatesRow(): void
    {
        $cartId = $this->getCartId();

        $this->getQuoteExtendedRepository()->saveValue($cartId, self::COLUMN_A, 'foobar');

        $row = $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
        $this->assertSame('foobar', $row->getData(self::COLUMN_A));
        $this->assertNull($row->getOrderId());
    }

    public function testSaveValueUpdatesExistingRow(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId, [self::COLUMN_A => 'first']);

        $this->getQuoteExtendedRepository()->saveValue($cartId, self::COLUMN_A, 'second');

        $this->assertSame(1, $this->countQuoteExtendedRows($cartId));
        $row = $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
        $this->assertSame((int)$saved->getId(), (int)$row->getId());
        $this->assertSame('second', $row->getData(self::COLUMN_A));
    }

    public function testSuccessiveSavesOfDifferentColumnsEndUpInOneRow(): void
    {
        $cartId = $this->getCartId();
        $repository = $this->getQuoteExtendedRepository();

        $repository->saveValue($cartId, self::COLUMN_A, 'a');
        $repository->saveValue($cartId, self::COLUMN_B, 'b');

        $this->assertSame(1, $this->countQuoteExtendedRows($cartId));
        $row = $repository->getByQuoteId($cartId);
        $this->assertSame('a', $row->getData(self::COLUMN_A));
        $this->assertSame('b', $row->getData(self::COLUMN_B));
    }

    public function testUnknownColumnIsNotSaved(): void
    {
        $cartId = $this->getCartId();

        $this->getQuoteExtendedRepository()->saveValue($cartId, 'loki_unknown_column', 'foobar');

        $this->assertSame(0, $this->countQuoteExtendedRows($cartId));
    }

    public function testReservedColumnIsIgnored(): void
    {
        $cartId = $this->getCartId();

        $this->getQuoteExtendedRepository()->saveValue($cartId, 'order_id', 42);

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
    }

    public function testLongStringIsCutToColumnLength(): void
    {
        $cartId = $this->getCartId();

        $this->getQuoteExtendedRepository()->saveValue($cartId, self::SHORT_COLUMN, 'abcdefghijklmnop');

        $row = $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
        $this->assertSame('abcdefghij', $row->getData(self::SHORT_COLUMN));
    }

    public function testSaveValueDetachesRowLinkedToOrder(): void
    {
        $cartId = $this->getCartId();
        $orderRow = $this->saveQuoteExtended($cartId, [self::COLUMN_A => 'order', self::COLUMN_B => 'kept']);
        $order = $this->placeOrder($cartId);

        $this->getQuoteExtendedRepository()->saveValue($cartId, self::COLUMN_A, 'quote');

        $oldRow = $this->loadQuoteExtended((int)$orderRow->getId());
        $this->assertNull($oldRow->getQuoteId());
        $this->assertSame((int)$order->getEntityId(), $oldRow->getOrderId());
        $this->assertSame('order', $oldRow->getData(self::COLUMN_A));
        $this->assertSame('kept', $oldRow->getData(self::COLUMN_B));

        $newRow = $this->getQuoteExtendedRepository()->getByQuoteId($cartId);
        $this->assertNotSame((int)$orderRow->getId(), (int)$newRow->getId());
        $this->assertNull($newRow->getOrderId());
        $this->assertSame('quote', $newRow->getData(self::COLUMN_A));
        $this->assertSame('kept', $newRow->getData(self::COLUMN_B));
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
