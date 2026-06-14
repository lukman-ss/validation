<?php

declare(strict_types=1);

namespace Lukman\Validation\Test;

use Lukman\Validation\Exception\ValidationRuleException;
use Lukman\Validation\RuleInterface;
use Lukman\Validation\ValidatorFactory;
use PHPUnit\Framework\TestCase;

class CustomRulesTest extends TestCase
{
    protected function setUp(): void
    {
        ValidatorFactory::$extensions = [];
        ValidatorFactory::$extensionMessages = [];
    }

    public function testRuleInterfaceObjectRunsAndUsesObjectMessage(): void
    {
        $rule = new class implements RuleInterface {
            public function passes(string $attribute, mixed $value, array $data): bool
            {
                return $value === 'ok';
            }

            public function message(string $attribute): string
            {
                return 'Object message for :attribute with :value.';
            }
        };

        $validator = (new ValidatorFactory())->make(['code' => 'bad'], ['code' => [$rule]]);

        $this->assertFalse($validator->passes());
        $this->assertSame('Object message for code with bad.', $validator->errors()->first('code'));
    }

    public function testClosureRuleResults(): void
    {
        $pass = (new ValidatorFactory())->make(['code' => 'ok'], [
            'code' => [
                static fn (string $attribute, mixed $value, array $data): bool => $value === 'ok',
            ],
        ]);

        $this->assertTrue($pass->passes());

        $fail = (new ValidatorFactory())->make(['code' => 'bad'], [
            'code' => [
                static fn (): bool => false,
            ],
        ]);

        $this->assertFalse($fail->passes());
        $this->assertSame('The code field is invalid.', $fail->errors()->first('code'));

        $message = (new ValidatorFactory())->make(['code' => 'bad'], [
            'code' => [
                static fn (): string => 'Closure message for :attribute.',
            ],
        ]);

        $this->assertFalse($message->passes());
        $this->assertSame('Closure message for code.', $message->errors()->first('code'));
    }

    public function testFactoryExtensionRunsWithParametersAndMessage(): void
    {
        ValidatorFactory::extend(
            'starts_with',
            static fn (string $attribute, mixed $value, array $parameters, array $data): bool => is_string($value)
                && isset($parameters[0])
                && str_starts_with($value, $parameters[0]),
            ':attribute must start with :value.'
        );

        $validator = (new ValidatorFactory())->make(['code' => 'abc'], ['code' => 'starts_with:z']);

        $this->assertFalse($validator->passes());
        $this->assertSame('code must start with abc.', $validator->errors()->first('code'));
    }

    public function testUnknownRuleThrowsValidationRuleException(): void
    {
        $this->expectException(ValidationRuleException::class);

        (new ValidatorFactory())->make(['code' => 'abc'], ['code' => 'unknown_rule'])->passes();
    }
}
