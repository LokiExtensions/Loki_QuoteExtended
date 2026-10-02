<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Integration;

use Loki\Components\Config\XmlConfig;
use Loki\QuoteExtended\Component\QuoteExtendedField\QuoteExtendedFieldRepository;
use Loki\QuoteExtended\Component\QuoteExtendedField\QuoteExtendedFieldViewModel;
use Loki\QuoteExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteExtended\Observer\AddQuoteExtendedToEmailVars;
use Loki\QuoteExtended\Observer\LinkOrderToQuoteExtended;
use LokiCheckout\Core\Config\LokiCheckoutModules;
use Magento\Cron\Model\ConfigInterface as CronConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsEnabled;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsRegistered;
use Yireo\IntegrationTestHelper\Test\Integration\Traits\AssertModuleIsRegisteredForReal;

class ModuleTest extends TestCase
{
    use AssertModuleIsEnabled;
    use AssertModuleIsRegistered;
    use AssertModuleIsRegisteredForReal;

    public function testModule(): void
    {
        $this->assertModuleIsEnabled('Loki_QuoteExtended');
        $this->assertModuleIsRegistered('Loki_QuoteExtended');
        $this->assertModuleIsRegisteredForReal('Loki_QuoteExtended');
    }

    public function testTable(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('loki_quote_extended');

        $this->assertTrue($connection->isTableExists($table));

        $columns = $connection->describeTable($table);
        foreach (['entity_id', 'quote_id', 'order_id', 'created_at', 'updated_at'] as $column) {
            $this->assertArrayHasKey($column, $columns);
        }

        $this->assertTrue((bool)$columns['quote_id']['NULLABLE']);
        $this->assertTrue((bool)$columns['order_id']['NULLABLE']);

        $uniqueColumns = [];
        foreach ($connection->getIndexList($table) as $index) {
            if ($index['INDEX_TYPE'] === 'unique') {
                $uniqueColumns = array_merge($uniqueColumns, $index['COLUMNS_LIST']);
            }
        }

        $this->assertContains('quote_id', $uniqueColumns);
        $this->assertContains('order_id', $uniqueColumns);

        $foreignKeys = [];
        foreach ($connection->getForeignKeys($table) as $foreignKey) {
            $foreignKeys[$foreignKey['COLUMN_NAME']] = $foreignKey;
        }

        $this->assertSame($resource->getTableName('quote'), $foreignKeys['quote_id']['REF_TABLE_NAME']);
        $this->assertSame('SET NULL', $foreignKeys['quote_id']['ON_DELETE']);
        $this->assertSame($resource->getTableName('sales_order'), $foreignKeys['order_id']['REF_TABLE_NAME']);
        $this->assertSame('CASCADE', $foreignKeys['order_id']['ON_DELETE']);
    }

    public function testObservers(): void
    {
        $eventConfig = Bootstrap::getObjectManager()->get(EventConfig::class);

        $this->assertObserver(
            $eventConfig->getObservers('sales_model_service_quote_submit_success'),
            LinkOrderToQuoteExtended::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('email_order_set_template_vars_before'),
            AddQuoteExtendedToEmailVars::class
        );
        $this->assertObserver(
            $eventConfig->getObservers('email_invoice_set_template_vars_before'),
            AddQuoteExtendedToEmailVars::class
        );
    }

    public function testCronJob(): void
    {
        $jobs = Bootstrap::getObjectManager()->get(CronConfig::class)->getJobs();

        $this->assertArrayHasKey('loki_quote_extended_delete_orphaned_rows', $jobs['default']);
        $this->assertSame(
            DeleteOrphanedRows::class,
            $jobs['default']['loki_quote_extended_delete_orphaned_rows']['instance']
        );
    }

    public function testLokiComponentGroup(): void
    {
        $groups = (array)Bootstrap::getObjectManager()->get(XmlConfig::class)->get('groups');

        $this->assertArrayHasKey('quoteExtendedFields', $groups);
        $group = $groups['quoteExtendedFields'];
        $this->assertSame(QuoteExtendedFieldRepository::class, $group['repository']);
        $this->assertSame(QuoteExtendedFieldViewModel::class, $group['viewModel']);
        $this->assertContains(
            'loki-checkout.sidebar.preview.quote_details',
            array_column($group['targets'], 'name')
        );
    }

    public function testLokiCheckoutModuleIsRegistered(): void
    {
        $modules = Bootstrap::getObjectManager()->get(LokiCheckoutModules::class)->getModules();

        $this->assertContains('Loki_QuoteExtended', $modules);
    }

    private function assertObserver(array $observers, string $observerClass): void
    {
        $this->assertContains($observerClass, array_column($observers, 'instance'));
    }
}
