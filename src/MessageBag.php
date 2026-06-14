<?php

declare(strict_types=1);

namespace Lukman\Validation;

use Countable;

class MessageBag implements Countable
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $messages = [];

    public function add(string $field, string $message): void
    {
        $this->messages[$field][] = $message;
    }

    /**
     * @return array<int, string>
     */
    public function get(string $field): array
    {
        return $this->messages[$field] ?? [];
    }

    public function first(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->messages[$field][0] ?? null;
        }

        foreach ($this->messages as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_merge(...array_values($this->messages ?: [[]]));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function toArray(): array
    {
        return $this->messages;
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function has(string $field): bool
    {
        return isset($this->messages[$field]) && !empty($this->messages[$field]);
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
