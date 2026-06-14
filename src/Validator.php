<?php

declare(strict_types=1);

namespace Lukman\Validation;

use Closure;
use Lukman\Validation\Exception\ValidationException;
use Lukman\Validation\Exception\ValidationRuleException;

class Validator
{
    private MessageBag $errors;

    /**
     * @var array<string, mixed>
     */
    private array $validated = [];

    private bool $hasValidated = false;

    /**
     * @var array<int, callable>
     */
    private array $afterCallbacks = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public function __construct(
        private array $data = [],
        private array $rules = [],
        private array $messages = [],
        private array $attributes = []
    ) {
        $this->errors = new MessageBag();
    }

    public function passes(): bool
    {
        if (!$this->hasValidated) {
            $this->validateData();
        }

        return $this->errors->isEmpty();
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): MessageBag
    {
        if (!$this->hasValidated) {
            $this->validateData();
        }

        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        if (!$this->hasValidated) {
            $this->validateData();
        }

        return $this->validated;
    }

    public function result(): ValidationResult
    {
        if (!$this->hasValidated) {
            $this->validateData();
        }

        return new ValidationResult(
            $this->errors->isEmpty(),
            $this->data,
            $this->validated,
            $this->errors
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return $this->validated;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateOrFail(): array
    {
        return $this->validate();
    }

    /**
     * @return array<string, mixed>
     */
    public function safe(): array
    {
        return $this->validated();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        $this->resetValidationState();

        return $this;
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        $this->resetValidationState();

        return $this;
    }

    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    private function validateData(): void
    {
        $this->errors->clear();
        $this->validated = [];

        $parser = new RuleParser();

        foreach ($this->rules as $field => $rules) {
            $parsedRules = $parser->parse($rules);
            $field = (string) $field;

            foreach ($this->expandField($field, $parsedRules) as $expandedField) {
                $this->validateField($expandedField, $parsedRules);
            }
        }

        $this->hasValidated = true;

        foreach ($this->afterCallbacks as $callback) {
            $callback($this);
        }

        foreach (array_keys($this->errors->toArray()) as $field) {
            $this->unsetValue($field);
        }
    }

    private function resetValidationState(): void
    {
        $this->errors->clear();
        $this->validated = [];
        $this->hasValidated = false;
    }

    /**
     * @param array<int, ParsedRule|RuleInterface|Closure> $rules
     * @return array<int, string>
     */
    private function expandField(string $field, array $rules): array
    {
        if (!str_contains($field, '*')) {
            return [$field];
        }

        $segments = explode('.', $field);
        $wildcardIndex = array_search('*', $segments, true);
        if ($wildcardIndex === false) {
            return [$field];
        }

        $parentPath = implode('.', array_slice($segments, 0, $wildcardIndex));
        $parentValue = $this->getValue($parentPath, $parentExists);

        if (!$parentExists) {
            return $this->hasRule($rules, 'required') ? [$field] : [];
        }

        if (!is_array($parentValue)) {
            $this->errors->add($field, $this->message($field, 'array', [], $parentValue, $parentPath));
            return [];
        }

        $expanded = [];
        foreach (array_keys($parentValue) as $index) {
            $concreteSegments = $segments;
            $concreteSegments[$wildcardIndex] = (string) $index;
            $expanded[] = implode('.', $concreteSegments);
        }

        return $expanded;
    }

    /**
     * @param array<int, ParsedRule|RuleInterface|Closure> $rules
     */
    private function validateField(string $field, array $rules): void
    {
        $value = $this->getValue($field, $exists);

        $isRequired = $this->hasRule($rules, 'required');
        $isNullable = $this->hasRule($rules, 'nullable');

        if (!$exists && !$isRequired) {
            return;
        }

        if ($isNullable && ($value === null || $value === '')) {
            $this->setValue($field, $value);
            return;
        }

        foreach ($rules as $rule) {
            if ($rule instanceof RuleInterface) {
                if (!$rule->passes($field, $value, $this->data)) {
                    $this->errors->add($field, $this->replacePlaceholders($rule->message($field), $field, [], $value));
                    return;
                }

                continue;
            }

            if ($rule instanceof Closure) {
                $result = $rule($field, $value, $this->data);

                if ($result === true || $result === null) {
                    continue;
                }

                $message = is_string($result) ? $result : 'The :attribute field is invalid.';
                $this->errors->add($field, $this->replacePlaceholders($message, $field, [], $value));
                return;
            }

            $ruleName = $rule->name();
            if ($ruleName === 'nullable') {
                continue;
            }

            if (!$this->validateRule($field, $ruleName, $value, $exists, $rule->parameters(), $rules)) {
                $this->errors->add($field, $this->message($field, $ruleName, $this->messageParameters($field, $ruleName, $rule->parameters()), $value));
                return;
            }
        }

        if ($exists) {
            $this->setValue($field, $value);
        }
    }

    /**
     * @param array<int, ParsedRule|RuleInterface|Closure> $rules
     */
    private function hasRule(array $rules, string $name): bool
    {
        foreach ($rules as $rule) {
            if ($rule instanceof ParsedRule && $rule->name() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $parameters
     * @param array<int, ParsedRule|RuleInterface|Closure> $rules
     */
    private function validateRule(string $field, string $rule, mixed $value, bool $exists, array $parameters, array $rules): bool
    {
        return match ($rule) {
            'required' => $exists && $value !== null && $value !== '',
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'numeric' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            'boolean' => is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1',
            'array' => is_array($value),
            'min' => $this->valueSize($value, $rules) >= $this->requiredNumericParameter($rule, $parameters, 0),
            'max' => $this->valueSize($value, $rules) <= $this->requiredNumericParameter($rule, $parameters, 0),
            'between' => $this->valueSize($value, $rules) >= $this->requiredNumericParameter($rule, $parameters, 0)
                && $this->valueSize($value, $rules) <= $this->requiredNumericParameter($rule, $parameters, 1),
            'size' => $this->valueSize($value, $rules) === $this->requiredNumericParameter($rule, $parameters, 0),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'in' => in_array((string) $value, $this->requiredParameters($rule, $parameters, 1), true),
            'not_in' => !in_array((string) $value, $this->requiredParameters($rule, $parameters, 1), true),
            'same' => $this->compareOtherField($value, $this->requiredParameter($rule, $parameters, 0), true),
            'different' => $this->compareOtherField($value, $this->requiredParameter($rule, $parameters, 0), false),
            'confirmed' => $this->compareOtherField($value, "{$field}_confirmation", true),
            default => $this->validateExtension($field, $rule, $value, $parameters),
        };
    }

    /**
     * @param array<int, string> $parameters
     */
    private function validateExtension(string $field, string $rule, mixed $value, array $parameters): bool
    {
        if (!isset(ValidatorFactory::$extensions[$rule])) {
            throw new ValidationRuleException("Unknown validation rule '{$rule}'.");
        }

        return (bool) ValidatorFactory::$extensions[$rule]($field, $value, $parameters, $this->data);
    }

    /**
     * @param array<int, ParsedRule|RuleInterface|Closure> $rules
     */
    private function valueSize(mixed $value, array $rules): float
    {
        if (is_array($value)) {
            return (float) count($value);
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && $this->hasRule($rules, 'numeric')) {
            return (float) $value;
        }

        if (is_string($value) && $this->hasRule($rules, 'integer')) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) strlen($value);
        }

        return 0.0;
    }

    /**
     * @param array<int, string> $parameters
     */
    private function requiredParameter(string $rule, array $parameters, int $index): string
    {
        if (!array_key_exists($index, $parameters) || $parameters[$index] === '') {
            throw new ValidationRuleException("Validation rule '{$rule}' requires parameter " . ($index + 1) . '.');
        }

        return $parameters[$index];
    }

    /**
     * @param array<int, string> $parameters
     * @return array<int, string>
     */
    private function requiredParameters(string $rule, array $parameters, int $minimum): array
    {
        $parameters = array_values(array_filter($parameters, static fn (string $parameter): bool => $parameter !== ''));

        if (count($parameters) < $minimum) {
            throw new ValidationRuleException("Validation rule '{$rule}' requires at least {$minimum} parameter.");
        }

        return $parameters;
    }

    /**
     * @param array<int, string> $parameters
     */
    private function requiredNumericParameter(string $rule, array $parameters, int $index): float
    {
        $parameter = $this->requiredParameter($rule, $parameters, $index);

        if (!is_numeric($parameter)) {
            throw new ValidationRuleException("Validation rule '{$rule}' parameter " . ($index + 1) . ' must be numeric.');
        }

        return (float) $parameter;
    }

    private function compareOtherField(mixed $value, string $otherField, bool $same): bool
    {
        $otherValue = $this->getValue($otherField, $otherExists);

        if (!$otherExists) {
            return !$same;
        }

        return $same ? $value === $otherValue : $value !== $otherValue;
    }

    /**
     * @param array<int, string> $parameters
     */
    private function message(string $field, string $rule, array $parameters = [], mixed $value = null, ?string $attributeField = null): string
    {
        $template = $this->messages["{$field}.{$rule}"]
            ?? $this->messages[$this->wildcardPattern($field) . ".{$rule}"]
            ?? $this->messages[$rule]
            ?? $this->defaultMessage($field, $rule);

        return $this->replacePlaceholders($template, $attributeField ?? $field, $parameters, $value);
    }

    /**
     * @param array<int, string> $parameters
     * @return array<int, string>
     */
    private function messageParameters(string $field, string $rule, array $parameters): array
    {
        if ($rule === 'confirmed') {
            return ["{$field}_confirmation"];
        }

        return $parameters;
    }

    private function defaultMessage(string $field, string $rule): string
    {
        $attribute = ':attribute';

        return match ($rule) {
            'required' => "The {$attribute} field is required.",
            'string' => "The {$attribute} must be a string.",
            'integer' => "The {$attribute} must be an integer.",
            'numeric' => "The {$attribute} must be a number.",
            'boolean' => "The {$attribute} must be true or false.",
            'array' => "The {$attribute} must be an array.",
            'min' => "The {$attribute} must be at least the minimum.",
            'max' => "The {$attribute} must not be greater than the maximum.",
            'between' => "The {$attribute} must be between the minimum and maximum.",
            'size' => "The {$attribute} must match the size.",
            'email' => "The {$attribute} must be a valid email address.",
            'url' => "The {$attribute} must be a valid URL.",
            'in', 'not_in' => "The selected {$attribute} is invalid.",
            'same' => "The {$attribute} must match the other field.",
            'different' => "The {$attribute} must be different from the other field.",
            'confirmed' => "The {$attribute} confirmation does not match.",
            default => ValidatorFactory::$extensionMessages[$rule] ?? "The {$attribute} field is invalid.",
        };
    }

    /**
     * @param array<int, string> $parameters
     */
    private function replacePlaceholders(string $message, string $field, array $parameters, mixed $value): string
    {
        $replacements = [
            ':attribute' => $this->attribute($field),
            ':value' => $this->stringValue($value),
            ':min' => $parameters[0] ?? '',
            ':max' => $parameters[1] ?? $parameters[0] ?? '',
            ':size' => $parameters[0] ?? '',
            ':other' => isset($parameters[0]) ? $this->attribute($parameters[0]) : '',
        ];

        return strtr($message, $replacements);
    }

    private function attribute(string $field): string
    {
        return $this->attributes[$field]
            ?? $this->attributes[$this->wildcardPattern($field)]
            ?? str_replace('_', ' ', $field);
    }

    private function wildcardPattern(string $field): string
    {
        $segments = explode('.', $field);

        foreach ($segments as &$segment) {
            if (ctype_digit($segment)) {
                $segment = '*';
            }
        }

        return implode('.', $segments);
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return 'array';
        }

        return (string) $value;
    }

    private function getValue(string $field, ?bool &$exists): mixed
    {
        if (array_key_exists($field, $this->data)) {
            $exists = true;
            return $this->data[$field];
        }

        $current = $this->data;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                $exists = false;
                return null;
            }

            $current = $current[$segment];
        }

        $exists = true;
        return $current;
    }

    private function setValue(string $field, mixed $value): void
    {
        $segments = explode('.', $field);
        $current = &$this->validated;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current = $value;
    }

    private function unsetValue(string $field): void
    {
        $segments = explode('.', $field);
        $current = &$this->validated;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return;
            }

            $current = &$current[$segment];
        }

        $last = end($segments);
        if (is_array($current) && $last !== false) {
            unset($current[$last]);
        }
    }
}
