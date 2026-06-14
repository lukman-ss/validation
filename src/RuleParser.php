<?php

declare(strict_types=1);

namespace Lukman\Validation;

use Lukman\Validation\Exception\ValidationRuleException;
use Closure;

class RuleParser
{
    /**
     * Parse the given rules.
     *
     * @return array<int, ParsedRule|RuleInterface|Closure>
     * @throws ValidationRuleException
     */
    public function parse(mixed $rules): array
    {
        if ($rules instanceof RuleInterface || $rules instanceof Closure) {
            return [$rules];
        }

        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        if (!is_array($rules)) {
            throw new ValidationRuleException('Invalid rules format.');
        }

        $parsed = [];
        foreach ($rules as $rule) {
            if ($rule instanceof RuleInterface || $rule instanceof Closure) {
                $parsed[] = $rule;
                continue;
            }

            if (is_string($rule)) {
                foreach (explode('|', $rule) as $rulePart) {
                    $trimmed = trim($rulePart);
                    if ($trimmed === '') {
                        continue;
                    }

                    $parsed[] = $this->parseRule($trimmed);
                }
            } else {
                throw new ValidationRuleException('Invalid rule item type.');
            }
        }

        return $parsed;
    }

    /**
     * Parse a single string rule into a ParsedRule.
     *
     * @throws ValidationRuleException
     */
    public function parseRule(string $rule): ParsedRule
    {
        $rule = trim($rule);
        if ($rule === '') {
            throw new ValidationRuleException('Cannot parse an empty rule.');
        }

        if (str_contains($rule, ':')) {
            $parts = explode(':', $rule, 2);
            $name = strtolower(trim($parts[0]));
            $paramString = trim($parts[1]);

            if ($name === '') {
                throw new ValidationRuleException('Invalid rule format: empty rule name.');
            }

            $parameters = array_map('trim', explode(',', $paramString));
            return new ParsedRule($name, $parameters);
        }

        $name = strtolower($rule);
        if ($name === '') {
            throw new ValidationRuleException('Invalid rule format: empty rule name.');
        }

        return new ParsedRule($name, []);
    }
}
