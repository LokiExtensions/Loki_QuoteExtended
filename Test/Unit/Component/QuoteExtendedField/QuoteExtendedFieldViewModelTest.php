<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Component\QuoteExtendedField;

use Loki\Components\Component\ComponentInterface;
use Loki\QuoteExtended\Component\QuoteExtendedField\QuoteExtendedFieldViewModel;
use LokiCheckout\Core\Component\Base\Generic\CheckoutContext;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

class QuoteExtendedFieldViewModelTest extends TestCase
{
    public function testGetStepReturnsResolvedStep(): void
    {
        $viewModel = $this->createViewModel(false, false);
        $viewModel->setStep('billing');

        $this->assertSame('billing', $viewModel->getStep());
    }

    public function testGetStepFallsBackToShippingForPhysicalQuote(): void
    {
        $viewModel = $this->createViewModel(false);
        $viewModel->setStep('none');

        $this->assertSame('shipping', $viewModel->getStep());
    }

    public function testGetStepFallsBackToBillingForVirtualQuote(): void
    {
        $viewModel = $this->createViewModel(true);
        $viewModel->setStep('none');

        $this->assertSame('billing', $viewModel->getStep());
    }

    private function createViewModel(bool $isVirtual, bool $expectQuote = true): QuoteExtendedFieldViewModel
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn($isVirtual);

        $context = $this->createMock(CheckoutContext::class);
        $context->expects($expectQuote ? $this->once() : $this->never())
            ->method('getQuote')->willReturn($quote);

        $component = $this->createMock(ComponentInterface::class);
        $component->method('getContext')->willReturn($context);

        $viewModel = new QuoteExtendedFieldViewModel();
        $viewModel->setComponent($component);

        return $viewModel;
    }
}
