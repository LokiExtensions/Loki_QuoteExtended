<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Observer;

use Loki\QuoteExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\CustomerCart as CustomerCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    AppIsolation(true),
    AppArea('frontend'),
]
class MergeQuoteExtendedTest extends TestCase
{
    use QuoteExtendedTestTrait;

    private const COLUMN_A = 'loki_merge_test_field_a';
    private const COLUMN_B = 'loki_merge_test_field_b';
    private const COLUMN_C = 'loki_merge_test_field_c';

    public static function setUpBeforeClass(): void
    {
        self::addQuoteExtendedTestColumn(self::COLUMN_A);
        self::addQuoteExtendedTestColumn(self::COLUMN_B);
        self::addQuoteExtendedTestColumn(self::COLUMN_C);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropQuoteExtendedTestColumn(self::COLUMN_A);
        self::dropQuoteExtendedTestColumn(self::COLUMN_B);
        self::dropQuoteExtendedTestColumn(self::COLUMN_C);
    }

    protected function tearDown(): void
    {
        $this->om()->get(CustomerSession::class)->setCustomerId(null);
        $checkoutSession = $this->om()->get(CheckoutSession::class);
        $checkoutSession->clearQuote();
        $checkoutSession->setCustomerData(null);
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(GuestCartFixture::class, as: 'guest_cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guest_cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
    ]
    public function testGuestRowIsKeptWhenCustomerHasNoCart(): void
    {
        $guestCartId = $this->getFixtureId('guest_cart');
        $guestRow = $this->saveQuoteExtended($guestCartId, [self::COLUMN_A => 'guest']);

        $quoteId = $this->loginAndLoadCustomerQuote($guestCartId);

        $quote = $this->om()->create(CartRepositoryInterface::class)->get($quoteId);
        $this->assertSame($this->getFixtureId('customer'), (int)$quote->getCustomerId());

        $row = $this->getQuoteExtendedRepository()->getByQuoteId($quoteId);
        $this->assertSame((int)$guestRow->getId(), (int)$row->getId());
        $this->assertSame('guest', $row->getData(self::COLUMN_A));
        $this->assertNull($row->getOrderId());

        $this->om()->get(DeleteOrphanedRows::class)->execute();
        $this->assertSame('guest', $this->loadQuoteExtended((int)$row->getId())->getData(self::COLUMN_A));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(GuestCartFixture::class, as: 'guest_cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guest_cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'customer_cart'),
    ]
    public function testGuestRowIsMovedToCustomerQuoteWithoutRow(): void
    {
        $guestCartId = $this->getFixtureId('guest_cart');
        $customerCartId = $this->getFixtureId('customer_cart');
        $guestRow = $this->saveQuoteExtended($guestCartId, [self::COLUMN_A => 'guest']);

        $quoteId = $this->loginAndLoadCustomerQuote($guestCartId);

        $this->assertSame($customerCartId, $quoteId);
        $row = $this->getQuoteExtendedRepository()->getByQuoteId($customerCartId);
        $this->assertSame((int)$guestRow->getId(), (int)$row->getId());
        $this->assertSame('guest', $row->getData(self::COLUMN_A));
        $this->assertSame(0, $this->countQuoteExtendedRows($guestCartId));

        $this->om()->get(DeleteOrphanedRows::class)->execute();
        $this->assertSame('guest', $this->loadQuoteExtended((int)$row->getId())->getData(self::COLUMN_A));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(GuestCartFixture::class, as: 'guest_cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guest_cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'customer_cart'),
    ]
    public function testGuestValuesAreMergedIntoCustomerRow(): void
    {
        $guestCartId = $this->getFixtureId('guest_cart');
        $customerCartId = $this->getFixtureId('customer_cart');
        $guestRow = $this->saveQuoteExtended($guestCartId, [
            self::COLUMN_A => 'guest',
            self::COLUMN_B => 'guest-only',
        ]);
        $customerRow = $this->saveQuoteExtended($customerCartId, [
            self::COLUMN_A => 'customer',
            self::COLUMN_C => 'customer-only',
        ]);

        $quoteId = $this->loginAndLoadCustomerQuote($guestCartId);

        $this->assertSame($customerCartId, $quoteId);
        $this->assertSame(1, $this->countQuoteExtendedRows($customerCartId));
        $this->assertSame(0, $this->countQuoteExtendedRows($guestCartId));
        $this->assertEmpty($this->loadQuoteExtended((int)$guestRow->getId())->getId());

        $row = $this->getQuoteExtendedRepository()->getByQuoteId($customerCartId);
        $this->assertSame((int)$customerRow->getId(), (int)$row->getId());
        $this->assertSame('guest', $row->getData(self::COLUMN_A));
        $this->assertSame('guest-only', $row->getData(self::COLUMN_B));
        $this->assertSame('customer-only', $row->getData(self::COLUMN_C));

        $this->om()->get(DeleteOrphanedRows::class)->execute();
        $row = $this->loadQuoteExtended((int)$customerRow->getId());
        $this->assertSame($customerCartId, $row->getQuoteId());
        $this->assertSame('guest', $row->getData(self::COLUMN_A));
        $this->assertSame('guest-only', $row->getData(self::COLUMN_B));
        $this->assertSame('customer-only', $row->getData(self::COLUMN_C));
    }

    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(GuestCartFixture::class, as: 'guest_cart'),
        DataFixture(AddProductToCartFixture::class, ['cart_id' => '$guest_cart.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(CustomerCartFixture::class, ['customer_id' => '$customer.id$'], as: 'customer_cart'),
    ]
    public function testEmptyGuestValueDoesNotOverwriteCustomerValue(): void
    {
        $guestCartId = $this->getFixtureId('guest_cart');
        $customerCartId = $this->getFixtureId('customer_cart');
        $this->saveQuoteExtended($guestCartId, [self::COLUMN_A => '', self::COLUMN_B => 'guest']);
        $this->saveQuoteExtended($customerCartId, [self::COLUMN_A => 'customer']);

        $this->loginAndLoadCustomerQuote($guestCartId);

        $row = $this->getQuoteExtendedRepository()->getByQuoteId($customerCartId);
        $this->assertSame('customer', $row->getData(self::COLUMN_A));
        $this->assertSame('guest', $row->getData(self::COLUMN_B));
    }

    private function loginAndLoadCustomerQuote(int $guestCartId): int
    {
        $checkoutSession = $this->om()->get(CheckoutSession::class);
        $checkoutSession->setQuoteId($guestCartId);
        $checkoutSession->getQuote();

        $customer = $this->om()->get(CustomerRepositoryInterface::class)->getById($this->getFixtureId('customer'));
        $this->om()->get(CustomerSession::class)->setCustomerDataAsLoggedIn($customer);
        $checkoutSession->loadCustomerQuote();

        return (int)$checkoutSession->getQuoteId();
    }

    private function getFixtureId(string $name): int
    {
        return (int)DataFixtureStorageManager::getStorage()->get($name)->getId();
    }
}
