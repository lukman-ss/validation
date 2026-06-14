<?php

declare(strict_types=1);

namespace Lukman\Validation;

class ValidatorFactory
{
    /**
     * @var array<string, callable>
     */
    public static array $extensions = [];

    /**
     * @var array<string, string>
     */
    public static array $extensionMessages = [];

    /**
     * Create a new Validator instance.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public function make(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = []
    ): Validator {
        return new Validator($data, $rules, $messages, $attributes);
    }

    /**
     * Register a custom validation rule.
     */
    public static function extend(string $name, callable $callback, ?string $message = null): void
    {
        $name = strtolower($name);

        self::$extensions[$name] = $callback;
        if ($message !== null) {
            self::$extensionMessages[$name] = $message;
        }
    }
}
