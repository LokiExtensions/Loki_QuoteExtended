<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Test\Unit\Cron;

use Loki\QuoteExtended\Cron\DeleteOrphanedRows;
use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;
use PHPUnit\Framework\TestCase;

class DeleteOrphanedRowsTest extends TestCase
{
    public function testExecuteDeletesOrphanedRows(): void
    {
        $resource = $this->createMock(QuoteExtendedResource::class);
        $resource->expects($this->once())->method('deleteOrphanedRows')->willReturn(3);

        (new DeleteOrphanedRows($resource))->execute();
    }
}
