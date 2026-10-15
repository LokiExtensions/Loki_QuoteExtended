<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\ViewModel\Adminhtml;

use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Loki\QuoteExtended\ViewModel\Adminhtml\OrderQuoteExtendedView;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\RequestInterface;
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
    AppArea('adminhtml'),
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

    protected function tearDown(): void
    {
        $this->setParams([]);
    }

    public function testGetValueOnOrderPage(): void
    {
        $saved = $this->saveQuoteExtended($this->getCartId());
        $order = $this->placeOrder($this->getCartId());

        $this->setParams(['order_id' => (int)$order->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);

        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$order->getEntityId(), $viewModel->getValue('order_id'));
    }

    public function testGetValueOnInvoicePage(): void
    {
        $saved = $this->saveQuoteExtended($this->getCartId());
        $order = $this->placeOrder($this->getCartId());
        $invoice = $this->createInvoice($order);

        $this->setParams(['invoice_id' => (int)$invoice->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);

        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$order->getEntityId(), $viewModel->getValue('order_id'));
    }

    public function testGetValueForOrderWithoutRow(): void
    {
        $order = $this->placeOrder($this->getCartId());

        $this->setParams(['order_id' => (int)$order->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);

        $this->assertSame('', $viewModel->getValue('entity_id'));
    }

    public function testGetValueForUnknownOrder(): void
    {
        $this->setParams(['order_id' => 999999]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);

        $this->assertSame('', $viewModel->getValue('entity_id'));
    }

    private function setParams(array $params): void
    {
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        foreach (['order_id', 'invoice_id'] as $name) {
            $request->setParam($name, $params[$name] ?? null);
        }
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
