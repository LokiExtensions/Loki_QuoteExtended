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
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testShipmentEmailContainsVariable(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $shipment = $this->createShipment($this->placeOrder($cartId));

        Bootstrap::getObjectManager()->get(ShipmentSender::class)->send($shipment, true);

        $this->assertTemplateVariable((int)$saved->getId());
    }

    public function testCreditmemoEmailContainsVariable(): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);
        $this->createInvoice($order);
        $creditmemo = $this->createCreditmemo($order);

        Bootstrap::getObjectManager()->get(CreditmemoSender::class)->send($creditmemo, true);

        $this->assertTemplateVariable((int)$saved->getId());
    }

    #[DataProvider('getEmailEvents')]
    public function testObserverIsRegisteredForEmailEvent(string $eventName): void
    {
        $cartId = $this->getCartId();
        $saved = $this->saveQuoteExtended($cartId);
        $order = $this->placeOrder($cartId);

        $transportObject = new DataObject(['order' => $order]);
        Bootstrap::getObjectManager()->get(EventManagerInterface::class)->dispatch(
            $eventName,
            ['transport' => $transportObject->getData(), 'transportObject' => $transportObject]
        );

        $quoteExtended = $transportObject->getData(AddQuoteExtendedToEmailVars::TEMPLATE_VARIABLE);
        $this->assertInstanceOf(QuoteExtended::class, $quoteExtended);
        $this->assertSame((int)$saved->getId(), (int)$quoteExtended->getId());
    }

    public static function getEmailEvents(): array
    {
        return [
            'order' => ['email_order_set_template_vars_before'],
            'invoice' => ['email_invoice_set_template_vars_before'],
            'shipment' => ['email_shipment_set_template_vars_before'],
            'creditmemo' => ['email_creditmemo_set_template_vars_before'],
        ];
    }

    public function testOrderEmailFindsRowNotLinkedYet(): void
    {
        $cartId = $this->getCartId();
        $order = $this->placeOrder($cartId);
        $saved = $this->saveQuoteExtended($cartId);

        Bootstrap::getObjectManager()->get(OrderSender::class)->send($order, true);

        $this->assertTemplateVariable((int)$saved->getId());
    }

    public function testOrderEmailUsesOwnRowAfterQuoteRestored(): void
    {
        $cartId = $this->getCartId();
        $first = $this->saveQuoteExtended($cartId);
        $firstOrder = $this->placeOrder($cartId);

        $this->restoreQuote($cartId);
        $second = $this->saveQuoteExtended($cartId);
        $secondOrder = $this->placeOrder($cartId);

        $sender = Bootstrap::getObjectManager()->get(OrderSender::class);

        $sender->send($firstOrder, true);
        $this->assertTemplateVariable((int)$first->getId());

        $sender->send($secondOrder, true);
        $this->assertTemplateVariable((int)$second->getId());
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
