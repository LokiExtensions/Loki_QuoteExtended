<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\ViewModel\Adminhtml;

use Loki\QuoteExtended\Config\LabelledColumns;
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

    private const COLUMN_A = 'loki_labelled_test_a';
    private const COLUMN_B = 'loki_labelled_test_b';
    private const COLUMN_UNREGISTERED = 'loki_labelled_test_unregistered';

    public static function setUpBeforeClass(): void
    {
        self::addQuoteExtendedTestColumn(self::COLUMN_A);
        self::addQuoteExtendedTestColumn(self::COLUMN_B);
        self::addQuoteExtendedTestColumn(self::COLUMN_UNREGISTERED);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropQuoteExtendedTestColumn(self::COLUMN_A);
        self::dropQuoteExtendedTestColumn(self::COLUMN_B);
        self::dropQuoteExtendedTestColumn(self::COLUMN_UNREGISTERED);
    }

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

    public function testGetValueOnShipmentPage(): void
    {
        $saved = $this->saveQuoteExtended($this->getCartId());
        $order = $this->placeOrder($this->getCartId());
        $shipment = $this->createShipment($order);

        $this->setParams(['shipment_id' => (int)$shipment->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);

        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$order->getEntityId(), $viewModel->getValue('order_id'));
    }

    public function testGetValueOnCreditmemoPage(): void
    {
        $saved = $this->saveQuoteExtended($this->getCartId());
        $order = $this->placeOrder($this->getCartId());
        $this->createInvoice($order);
        $creditmemo = $this->createCreditmemo($order);

        $this->setParams(['creditmemo_id' => (int)$creditmemo->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);

        $this->assertSame((string)$saved->getId(), $viewModel->getValue('entity_id'));
        $this->assertSame((string)$order->getEntityId(), $viewModel->getValue('order_id'));
    }

    public function testGetValueForUnknownShipmentAndCreditmemo(): void
    {
        $this->setParams(['shipment_id' => 999999]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);
        $this->assertSame('', $viewModel->getValue('entity_id'));

        $this->setParams(['creditmemo_id' => 999999]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);
        $this->assertSame('', $viewModel->getValue('entity_id'));
    }

    public function testEachOrderShowsOwnRowAfterQuoteRestored(): void
    {
        $cartId = $this->getCartId();
        $first = $this->saveQuoteExtended($cartId);
        $firstOrder = $this->placeOrder($cartId);
        $firstInvoice = $this->createInvoice($firstOrder);

        $this->restoreQuote($cartId);
        $second = $this->saveQuoteExtended($cartId);
        $secondOrder = $this->placeOrder($cartId);

        $this->assertNotSame((int)$first->getId(), (int)$second->getId());

        $this->setParams(['invoice_id' => (int)$firstInvoice->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);
        $this->assertSame((string)$first->getId(), $viewModel->getValue('entity_id'));

        $this->setParams(['order_id' => (int)$firstOrder->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);
        $this->assertSame((string)$first->getId(), $viewModel->getValue('entity_id'));

        $this->setParams(['order_id' => (int)$secondOrder->getEntityId()]);
        $viewModel = Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class);
        $this->assertSame((string)$second->getId(), $viewModel->getValue('entity_id'));
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

    public function testGetLabelledValuesReturnsOnlyRegisteredNonEmptyValues(): void
    {
        $this->saveQuoteExtended($this->getCartId(), [
            self::COLUMN_A => 'foo',
            self::COLUMN_B => '',
            self::COLUMN_UNREGISTERED => 'bar',
        ]);
        $order = $this->placeOrder($this->getCartId());

        $this->setParams(['order_id' => (int)$order->getEntityId()]);
        $viewModel = $this->createViewModelWithLabelledColumns();

        $this->assertSame([['label' => 'Label A', 'value' => 'foo']], $viewModel->getLabelledValues());
    }

    public function testGetLabelledValuesKeepsConfiguredOrder(): void
    {
        $this->saveQuoteExtended($this->getCartId(), [self::COLUMN_A => 'foo', self::COLUMN_B => 'bar']);
        $order = $this->placeOrder($this->getCartId());

        $this->setParams(['order_id' => (int)$order->getEntityId()]);
        $viewModel = $this->createViewModelWithLabelledColumns();

        $this->assertSame([
            ['label' => 'Label B', 'value' => 'bar'],
            ['label' => 'Label A', 'value' => 'foo'],
        ], $viewModel->getLabelledValues());
    }

    public function testGetLabelledValuesForOrderWithoutRow(): void
    {
        $order = $this->placeOrder($this->getCartId());

        $this->setParams(['order_id' => (int)$order->getEntityId()]);
        $viewModel = $this->createViewModelWithLabelledColumns();

        $this->assertSame([], $viewModel->getLabelledValues());
    }

    private function createViewModelWithLabelledColumns(): OrderQuoteExtendedView
    {
        $labelledColumns = Bootstrap::getObjectManager()->create(LabelledColumns::class, [
            'columns' => [self::COLUMN_B => 'Label B', self::COLUMN_A => 'Label A', 'entity_id' => 'Entity'],
        ]);

        return Bootstrap::getObjectManager()->create(OrderQuoteExtendedView::class, [
            'labelledColumns' => $labelledColumns,
        ]);
    }

    private function setParams(array $params): void
    {
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        foreach (['order_id', 'invoice_id', 'shipment_id', 'creditmemo_id'] as $name) {
            $request->setParam($name, $params[$name] ?? null);
        }
    }

    private function getCartId(): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
    }
}
