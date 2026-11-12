<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Observer;

use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended\Collection;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
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
    DataFixture(GuestCartFixture::class, as: 'cart2'),
    DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart2.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart2.id$']),
    DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart2.id$']),
]
class LinkOrderToQuoteExtendedTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private const COLUMN = 'loki_link_order_test_field';

    private DataFixtureStorage $fixtures;

    public static function setUpBeforeClass(): void
    {
        self::addQuoteExtendedTestColumn(self::COLUMN);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropQuoteExtendedTestColumn(self::COLUMN);
    }

    protected function setUp(): void
    {
        $this->fixtures = DataFixtureStorageManager::getStorage();
    }

    public function testPlacingOrderLinksRowToOrder(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);

        $order = $this->placeOrder($cartId);

        $row = $this->loadQuoteExtended((int)$saved->getId());
        $this->assertSame((int)$order->getEntityId(), $row->getOrderId());
        $this->assertSame($cartId, $row->getQuoteId());
    }

    public function testPlacingOrderWithoutRowCreatesNothing(): void
    {
        $cartId = $this->getCartId();
        $order = $this->placeOrder($cartId);

        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteExtendedRepository()->getByOrderId((int)$order->getEntityId());
    }

    public function testRestoredQuoteKeepsDataOfFirstOrder(): void
    {
        $cartId = $this->getCartId();
        $first = $this->saveQuoteExtended($cartId);
        $firstOrder = $this->placeOrder($cartId);

        $this->restoreQuote($cartId);
        $secondOrder = $this->placeOrder($cartId);

        $this->assertNotSame((int)$firstOrder->getEntityId(), (int)$secondOrder->getEntityId());

        $firstRow = $this->getQuoteExtendedRepository()->getByOrderId((int)$firstOrder->getEntityId());
        $this->assertSame((int)$first->getId(), (int)$firstRow->getId());
        $this->assertNull($firstRow->getQuoteId());

        $secondRow = $this->getQuoteExtendedRepository()->getByOrderId((int)$secondOrder->getEntityId());
        $this->assertNotSame((int)$first->getId(), (int)$secondRow->getId());
        $this->assertSame($cartId, $secondRow->getQuoteId());

        $this->assertSame(
            (int)$firstRow->getId(),
            (int)$this->getQuoteExtendedRepository()->getByOrder($firstOrder)->getId()
        );
        $this->assertSame(
            (int)$secondRow->getId(),
            (int)$this->getQuoteExtendedRepository()->getByOrder($secondOrder)->getId()
        );
    }

    public function testDispatchingEventTwiceKeepsSingleRow(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        $quote = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class)->get($cartId);
        Bootstrap::getObjectManager()->get(EventManagerInterface::class)->dispatch(
            'sales_model_service_quote_submit_success',
            ['order' => $order, 'quote' => $quote]
        );

        $rows = Bootstrap::getObjectManager()->create(Collection::class)
            ->addFieldToFilter('order_id', (int)$order->getEntityId());
        $this->assertCount(1, $rows);

        $row = $this->loadQuoteExtended((int)$saved->getId());
        $this->assertSame((int)$order->getEntityId(), $row->getOrderId());
        $this->assertSame($cartId, $row->getQuoteId());
    }

    public function testEditedOrderGetsCopyOfParentOrderRow(): void
    {
        $parentCartId = $this->getCartId();
        $parentRow = $this->saveQuoteExtended($parentCartId, [self::COLUMN => 'foobar']);
        $parentOrder = $this->placeOrder($parentCartId);

        $cartId = $this->getCartId('cart2');
        $order = $this->placeOrder($cartId);
        $this->assertSame(0, $this->countQuoteExtendedRows($cartId));

        $this->dispatchQuoteSubmitSuccess($order, $cartId, (int)$parentOrder->getEntityId());

        $row = $this->getQuoteExtendedRepository()->getByOrderId((int)$order->getEntityId());
        $this->assertNotSame((int)$parentRow->getId(), (int)$row->getId());
        $this->assertSame($cartId, $row->getQuoteId());
        $this->assertSame((int)$order->getEntityId(), $row->getOrderId());
        $this->assertSame('foobar', $row->getData(self::COLUMN));

        $parent = $this->loadQuoteExtended((int)$parentRow->getId());
        $this->assertSame($parentCartId, $parent->getQuoteId());
        $this->assertSame((int)$parentOrder->getEntityId(), $parent->getOrderId());
        $this->assertSame('foobar', $parent->getData(self::COLUMN));
    }

    public function testEditedOrderWithParentWithoutRowCreatesNothing(): void
    {
        $parentOrder = $this->placeOrder($this->getCartId());

        $cartId = $this->getCartId('cart2');
        $order = $this->placeOrder($cartId);

        $this->dispatchQuoteSubmitSuccess($order, $cartId, (int)$parentOrder->getEntityId());

        $this->assertSame(0, $this->countQuoteExtendedRows($cartId));
    }

    public function testOrderWithoutParentDoesNotCopyRowOfOtherOrder(): void
    {
        $this->saveQuoteExtended($this->getCartId(), [self::COLUMN => 'foobar']);
        $this->placeOrder($this->getCartId());

        $cartId = $this->getCartId('cart2');
        $order = $this->placeOrder($cartId);

        $this->dispatchQuoteSubmitSuccess($order, $cartId, null);

        $this->assertSame(0, $this->countQuoteExtendedRows($cartId));
        $this->expectException(NoSuchEntityException::class);
        $this->getQuoteExtendedRepository()->getByOrderId((int)$order->getEntityId());
    }

    private function dispatchQuoteSubmitSuccess(OrderInterface $order, int $cartId, ?int $parentOrderId): void
    {
        $order->setRelationParentId($parentOrderId);
        $order = Bootstrap::getObjectManager()->create(OrderRepositoryInterface::class)->save($order);

        $quote = Bootstrap::getObjectManager()->create(CartRepositoryInterface::class)->get($cartId);
        Bootstrap::getObjectManager()->get(EventManagerInterface::class)->dispatch(
            'sales_model_service_quote_submit_success',
            ['order' => $order, 'quote' => $quote]
        );
    }

    private function getCartId(string $name = 'cart'): int
    {
        return (int)$this->fixtures->get($name)->getId();
    }
}
