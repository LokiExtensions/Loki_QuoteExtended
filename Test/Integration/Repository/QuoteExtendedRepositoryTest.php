<?php
declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration\Repository;

use Loki\QuoteExtended\Repository\QuoteExtendedRepository;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

#[DbIsolation(false)]
class QuoteExtendedRepositoryTest extends TestCase
{
    public function testSaveAndLoad(): void
    {
        $quote = $this->createQuote();
        $repository = $this->getRepository();

        $model = $repository->create((int)$quote->getId());
        $repository->save($model);
        $this->assertGreaterThan(0, (int)$model->getId());

        $loaded = $repository->get((int)$model->getId());
        $this->assertSame((int)$quote->getId(), $loaded->getQuoteId());
        $this->assertNotEmpty($loaded->getCreatedAt());

        $loadedByQuote = $repository->getByQuoteId((int)$quote->getId());
        $this->assertSame((int)$model->getId(), (int)$loadedByQuote->getId());

        $this->deleteQuote($quote);
    }

    public function testGetOrCreateByQuoteIdReturnsUnsavedModel(): void
    {
        $model = $this->getRepository()->getOrCreateByQuoteId(999999);
        $this->assertNull($model->getId());
        $this->assertSame(999999, $model->getQuoteId());
    }

    public function testGetThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->getRepository()->get(999999);
    }

    public function testGetByQuoteIdThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->getRepository()->getByQuoteId(999999);
    }

    public function testDelete(): void
    {
        $quote = $this->createQuote();
        $repository = $this->getRepository();

        $model = $repository->save($repository->create((int)$quote->getId()));
        $id = (int)$model->getId();
        $repository->delete($model);

        $this->expectException(NoSuchEntityException::class);
        try {
            $repository->get($id);
        } finally {
            $this->deleteQuote($quote);
        }
    }

    public function testRowIsDeletedWithQuote(): void
    {
        $quote = $this->createQuote();
        $repository = $this->getRepository();

        $model = $repository->save($repository->create((int)$quote->getId()));
        $this->deleteQuote($quote);

        $this->expectException(NoSuchEntityException::class);
        $repository->get((int)$model->getId());
    }

    private function getRepository(): QuoteExtendedRepository
    {
        return ObjectManager::getInstance()->create(QuoteExtendedRepository::class);
    }

    private function createQuote(): Quote
    {
        $objectManager = ObjectManager::getInstance();
        $quote = $objectManager->get(QuoteFactory::class)->create();
        $quote->setStoreId(1);
        $objectManager->get(QuoteResourceModel::class)->save($quote);

        return $quote;
    }

    private function deleteQuote(Quote $quote): void
    {
        ObjectManager::getInstance()->get(QuoteResourceModel::class)->delete($quote);
    }
}
