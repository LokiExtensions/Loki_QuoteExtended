<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Adminhtml;

use Loki\QuoteExtended\Config\LabelledColumns;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Loki\QuoteExtended\ViewModel\Adminhtml\OrderQuoteExtendedView;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Test\Fixture\Creditmemo as CreditmemoFixture;
use Magento\Sales\Test\Fixture\Invoice as InvoiceFixture;
use Magento\Sales\Test\Fixture\Shipment as ShipmentFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;

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
    DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], as: 'order'),
    DataFixture(InvoiceFixture::class, ['order_id' => '$order.id$'], as: 'invoice'),
    DataFixture(ShipmentFixture::class, ['order_id' => '$order.id$'], as: 'shipment'),
    DataFixture(CreditmemoFixture::class, ['order_id' => '$order.id$'], as: 'creditmemo'),
]
class OrderInfoBlockTest extends AbstractBackendController
{
    use QuoteExtendedTestTrait;

    private const BLOCK_NAME = 'loki-quote-extended.order.info';
    private const COLUMN = 'loki_order_info_test_field';
    private const UNREGISTERED_COLUMN = 'loki_order_info_test_unregistered';
    private const ROW_PATTERN = '#<tr>\s*<th>Order info label</th>\s*<td>Hello &lt;b&gt;world&lt;/b&gt;</td>\s*</tr>#';

    public static function setUpBeforeClass(): void
    {
        self::addQuoteExtendedTestColumn(self::COLUMN);
        self::addQuoteExtendedTestColumn(self::UNREGISTERED_COLUMN);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropQuoteExtendedTestColumn(self::COLUMN);
        self::dropQuoteExtendedTestColumn(self::UNREGISTERED_COLUMN);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $labelledColumns = $this->om()->create(LabelledColumns::class, [
            'columns' => [self::COLUMN => 'Order info label'],
        ]);
        $this->om()->addSharedInstance($labelledColumns, LabelledColumns::class);
    }

    protected function tearDown(): void
    {
        $this->om()->removeSharedInstance(LabelledColumns::class);
        parent::tearDown();
    }

    #[DataProvider('getPages')]
    public function testPageRendersLabelledValues(string $path, string $fixture, string $param): void
    {
        $this->saveValues([self::COLUMN => 'Hello <b>world</b>', self::UNREGISTERED_COLUMN => 'Hidden value']);

        $this->dispatchPage($path, $fixture, $param);

        $body = $this->getResponse()->getBody();
        $this->assertMatchesRegularExpression(self::ROW_PATTERN, $body);
        $this->assertStringNotContainsString('Hidden value', $body);
        $this->assertStringNotContainsString('Hello <b>world</b>', $body);
    }

    #[DataProvider('getPages')]
    public function testPageContainsBlock(string $path, string $fixture, string $param): void
    {
        $this->dispatchPage($path, $fixture, $param);

        $block = $this->om()->get(LayoutInterface::class)->getBlock(self::BLOCK_NAME);
        $this->assertInstanceOf(Template::class, $block);
        $this->assertSame('Loki_QuoteExtended::order/info.phtml', $block->getTemplate());
        $this->assertInstanceOf(OrderQuoteExtendedView::class, $block->getData('view_model'));
        $this->assertStringNotContainsString('Order info label', $this->getResponse()->getBody());
    }

    public static function getPages(): array
    {
        return [
            'order' => ['backend/sales/order/view/order_id/', 'order', 'order_id'],
            'invoice' => ['backend/sales/invoice/view/invoice_id/', 'invoice', 'invoice_id'],
            'shipment' => ['backend/admin/order_shipment/view/shipment_id/', 'shipment', 'shipment_id'],
            'creditmemo' => ['backend/sales/creditmemo/view/creditmemo_id/', 'creditmemo', 'creditmemo_id'],
        ];
    }

    private function dispatchPage(string $path, string $fixture, string $param): void
    {
        $entityId = (int)DataFixtureStorageManager::getStorage()->get($fixture)->getId();
        $this->dispatch($path . $entityId);

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    private function saveValues(array $data): void
    {
        $order = DataFixtureStorageManager::getStorage()->get('order');
        $this->saveQuoteExtended((int)$order->getQuoteId(), array_merge($data, [
            'order_id' => (int)$order->getEntityId(),
        ]));
    }
}
