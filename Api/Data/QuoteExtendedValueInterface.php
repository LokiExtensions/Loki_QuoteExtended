<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Api\Data;

interface QuoteExtendedValueInterface
{
    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @return string|null
     */
    public function getValue(): ?string;
}
