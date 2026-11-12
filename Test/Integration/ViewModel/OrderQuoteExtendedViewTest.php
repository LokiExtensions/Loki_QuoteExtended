<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\ViewModel;

use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Loki\QuoteExtended\ViewModel\OrderQuoteExtendedView;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\Registry;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
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
class OrderQuoteExtendedViewTest extends TestCase
{
    use QuoteExtendedTestTrait;

    protected function setUp(): void
    {
        $this->resetState();
    }

    protected function tearDown(): void
    {
        $this->resetState();
    }

    public function testGetValueFromCurrentOrderInRegistry(): void
    {
        $saved = $this->saveQuoteExtended($this->getCartId());
        $order = $this->placeOrder($this->getCartId());
        $this->resetState();

        $this->om()->get(Registry::class)->register('current_order', $order);

        $viewModel = $this->om()->create(OrderQuoteExtendedView::class);
        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$order->getEntityId(), $viewModel->getValue('order_id'));
    }

    public function testGetValueFromLastRealOrderInCheckoutSession(): void
    {
        $saved = $this->saveQuoteExtended($this->getCartId());
        $order = $this->placeOrder($this->getCartId());
        $this->resetState();

        $this->om()->get(CheckoutSession::class)->setLastRealOrderId($order->getIncrementId());

        $viewModel = $this->om()->create(OrderQuoteExtendedView::class);
        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$order->getEntityId(), $viewModel->getValue('order_id'));
    }

    public function testRegistryOrderTakesPrecedenceOverCheckoutSession(): void
    {
        $cartId = $this->getCartId();
        $first = $this->saveQuoteExtended($cartId);
        $firstOrder = $this->placeOrder($cartId);

        $this->restoreQuote($cartId);
        $second = $this->saveQuoteExtended($cartId);
        $secondOrder = $this->placeOrder($cartId);

        $this->om()->get(CheckoutSession::class)->setLastRealOrderId($secondOrder->getIncrementId());
        $this->om()->get(Registry::class)->register('current_order', $firstOrder);

        $viewModel = $this->om()->create(OrderQuoteExtendedView::class);
        $this->assertSame((string)$first->getId(), $viewModel->getValue('entity_id'));
        $this->assertNotSame((string)$second->getId(), $viewModel->getValue('entity_id'));
    }

    public function testGetValueForOrderWithoutRow(): void
    {
        $order = $this->placeOrder($this->getCartId());
        $this->resetState();

        $this->om()->get(Registry::class)->register('current_order', $order);

        $viewModel = $this->om()->create(OrderQuoteExtendedView::class);
        $this->assertSame('', $viewModel->getValue('entity_id'));
    }

    public function testGetValueWithoutOrder(): void
    {
        $this->saveQuoteExtended($this->getCartId());

        $viewModel = $this->om()->create(OrderQuoteExtendedView::class);
        $this->assertSame('', $viewModel->getValue('entity_id'));
    }

    private function resetState(): void
    {
        $this->om()->get(Registry::class)->unregister('current_order');
        $this->om()->get(CheckoutSession::class)->setLastRealOrderId(null);
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
