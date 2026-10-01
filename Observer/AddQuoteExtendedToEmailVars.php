<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Observer;

use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

class AddQuoteExtendedToEmailVars implements ObserverInterface
{
    public const TEMPLATE_VARIABLE = 'loki_quote_extended';

    public function __construct(
        private readonly QuoteExtendedRepository $quoteExtendedRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        $transport = $observer->getEvent()->getData('transportObject');
        if (false === $transport instanceof DataObject) {
            return;
        }

        $order = $transport->getData('order');
        if (false === $order instanceof OrderInterface) {
            return;
        }

        try {
            $quoteExtended = $this->quoteExtendedRepository->getByOrder($order);
        } catch (NoSuchEntityException) {
            return;
        }

        $transport->setData(self::TEMPLATE_VARIABLE, $quoteExtended);
    }
}
