<?php

declare(strict_types=1);

namespace Loki\QuoteExtended\Model;

use Loki\QuoteExtended\Api\Data\QuoteExtendedValueInterface;

class QuoteExtendedValue implements QuoteExtendedValueInterface
{
    public function __construct(
        private readonly string $code = '',
        private readonly ?string $value = null
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
