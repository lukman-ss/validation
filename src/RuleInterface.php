<?php

declare(strict_types=1);

namespace Lukman\Validation;

interface RuleInterface
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes(string $attribute, mixed $value, array $data): bool;

    /**
     * Get the validation error message.
     */
    public function message(string $attribute): string;
}
