<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Cron;

use Loki\QuoteExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteExtended\Model\QuoteExtended;
use Loki\QuoteExtended\Test\Integration\Trait\QuoteExtendedTestTrait;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[
    DbIsolation(true),
    DataFixture(GuestCartFixture::class, as: 'cart'),
]
class DeleteOrphanedRowsTest extends TestCase
{
    use QuoteExtendedTestTrait;

    public function testDeletesOnlyRowsWithoutQuoteAndOrder(): void
    {
        $cartId = (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
        $kept = $this->saveQuoteExtended($cartId);

        $orphan = Bootstrap::getObjectManager()->create(QuoteExtended::class);
        $orphan->setQuoteId(null);
        $orphan->setOrderId(null);
        $this->getQuoteExtendedRepository()->save($orphan);
        $this->assertNotEmpty($orphan->getId());

        Bootstrap::getObjectManager()->get(DeleteOrphanedRows::class)->execute();

        $this->assertEmpty($this->loadQuoteExtended((int)$orphan->getId())->getId());
        $this->assertNotEmpty($this->loadQuoteExtended((int)$kept->getId())->getId());
    }
}
