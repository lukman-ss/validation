<?php

declare(strict_types=1);

namespace Lukman\Validation;

class ValidationResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $validated
     */
    public function __construct(
        private bool $passes,
        private array $data,
        private array $validated,
        private MessageBag $errors
    ) {
    }

    public function passes(): bool
    {
        return $this->passes;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validated;
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }
}
