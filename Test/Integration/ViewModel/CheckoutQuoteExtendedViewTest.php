<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\ViewModel;

use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Loki\QuoteExtended\ViewModel\CheckoutQuoteExtendedView;
use LokiCheckout\Core\ViewModel\CheckoutState;
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
