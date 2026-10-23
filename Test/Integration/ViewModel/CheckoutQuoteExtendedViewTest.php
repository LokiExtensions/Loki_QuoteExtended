<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\ViewModel;

use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Loki\QuoteExtended\ViewModel\CheckoutQuoteExtendedView;
use LokiCheckout\Core\ViewModel\CheckoutState;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    AppArea('frontend'),
    DataFixture(GuestCartFixture::class, as: 'cart'),
    DataFixture(GuestCartFixture::class, as: 'other_cart'),
]
class CheckoutQuoteExtendedViewTest extends TestCase
{
    use QuoteExtendedTestTrait;

    public function testGetValueReadsRowOfCurrentQuote(): void
    {
        $cartId = $this->getCartId('cart');
        $saved = $this->saveQuoteExtended($cartId);

        $viewModel = $this->createViewModel($cartId);

        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$cartId, $viewModel->getValue('quote_id'));
        $this->assertSame('', $viewModel->getValue('order_id'));
    }

    public function testGetValueIgnoresRowOfOtherQuote(): void
    {
        $this->saveQuoteExtended($this->getCartId('other_cart'));

        $viewModel = $this->createViewModel($this->getCartId('cart'));

        $this->assertSame('', $viewModel->getValue('entity_id'));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'order_cart'),
        DataFixture(
            AddProductToCartFixture::class,
            ['cart_id' => '$order_cart.id$', 'product_id' => '$product.id$', 'qty' => 1]
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$order_cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$order_cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$order_cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$order_cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$order_cart.id$']),
    ]
    public function testGetValueAfterOrderPlacedReturnsLinkedRow(): void
    {
        $cartId = $this->getCartId('order_cart');
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        $viewModel = $this->createViewModel($cartId);

        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$order->getEntityId(), $viewModel->getValue('order_id'));
    }

    private function createViewModel(int $cartId): CheckoutQuoteExtendedView
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(CheckoutState::class)->setQuoteId($cartId);

        return $objectManager->create(CheckoutQuoteExtendedView::class);
    }

    private function getCartId(string $name): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get($name)->getId();
    }
}
