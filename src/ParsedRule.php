<?php

declare(strict_types=1);

namespace Lukman\Validation;

class ParsedRule
{
    /**
     * @param array<int, string> $parameters
     */
    public function __construct(
        private string $name,
        private array $parameters = []
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
