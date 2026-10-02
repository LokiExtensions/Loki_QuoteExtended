<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Observer;

use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Observer\AddQuoteExtendedToEmailVars;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Model\Order\Email\Container\Template as TemplateContainer;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\TestFramework\Fixture\AppArea;
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
#[AppArea('frontend')]
class AddQuoteExtendedToEmailVarsTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private DataFixtureStorage $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = DataFixtureStorageManager::getStorage();
    }

    public function testOrderEmailContainsVariable(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $this->assertTemplateVariable((int)$saved->getId());
    }

    public function testInvoiceEmailContainsVariable(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);
        $invoice = $this->createInvoice($order);

        Bootstrap::getObjectManager()->get(InvoiceSender::class)->send($invoice, true);

        $this->assertTemplateVariable((int)$saved->getId());
    }

    public function testOrderEmailFindsRowNotLinkedYet(): void
    {
        $cartId = $this->getCartId();
        $order = $this->placeOrder($cartId);
        $saved = $this->saveQuoteExtended($cartId);

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $this->assertTemplateVariable((int)$saved->getId());
    }

    public function testOrderEmailWithoutRowHasNoVariable(): void
    {
        $order = $this->placeOrder($this->getCartId());

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $templateVars = Bootstrap::getObjectManager()->get(TemplateContainer::class)->getTemplateVars();
        $this->assertArrayHasKey('order', $templateVars);
        $this->assertArrayNotHasKey(AddQuoteExtendedToEmailVars::TEMPLATE_VARIABLE, $templateVars);
    }

    private function assertTemplateVariable(int $expectedEntityId): void
    {
        $templateVars = Bootstrap::getObjectManager()->get(TemplateContainer::class)->getTemplateVars();

        $this->assertArrayHasKey(AddQuoteExtendedToEmailVars::TEMPLATE_VARIABLE, $templateVars);
        $quoteExtended = $templateVars[AddQuoteExtendedToEmailVars::TEMPLATE_VARIABLE];
        $this->assertInstanceOf(QuoteExtended::class, $quoteExtended);
        $this->assertSame($expectedEntityId, (int)$quoteExtended->getId());
    }

    private function getCartId(): int
    {
        return (int)$this->fixtures->get('cart')->getId();
    }
}
