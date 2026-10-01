<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Cron;

use Loki\QuoteExtended\Model\ResourceModel\QuoteExtended as QuoteExtendedResource;

class DeleteOrphanedRows
{
    public function __construct(
        private readonly QuoteExtendedResource $resource
    ) {
    }

    public function execute(): void
    {
        $this->resource->deleteOrphanedRows();
    }
}
