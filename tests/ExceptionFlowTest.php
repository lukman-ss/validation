<?php

declare(strict_types=1);

namespace Lukman\Validation\Test;

use Lukman\Validation\Exception\ValidationException;
use Lukman\Validation\MessageBag;
use Lukman\Validation\Validator;
use Lukman\Validation\ValidatorFactory;
use PHPUnit\Framework\TestCase;

class ExceptionFlowTest extends TestCase
{
    public function testValidateReturnsValidatedDataAndThrowsWithMessageBag(): void
    {
        $factory = new ValidatorFactory();

        $this->assertSame(['name' => 'John'], $factory->make(['name' => 'John'], ['name' => 'required|string'])->validate());

        $validator = $factory->make(['name' => ''], ['name' => 'required']);

        try {
            $validator->validate();
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertInstanceOf(MessageBag::class, $exception->errors());
            $this->assertSame('The name field is required.', $exception->errors()->first('name'));
        }
    }

    public function testValidateOrFailAliasAndSafeDoesNotThrow(): void
    {
        $factory = new ValidatorFactory();

        $this->assertSame(['email' => 'valid@example.com'], $factory->make(
            ['email' => 'valid@example.com'],
            ['email' => 'required|email']
        )->validateOrFail());

        $validator = $factory->make(['email' => 'bad', 'name' => 'John'], [
            'email' => 'email',
            'name' => 'required|string',
        ]);

        $this->assertSame(['name' => 'John'], $validator->safe());
    }

    public function testSetDataAndSetRulesResetState(): void
    {
        $validator = (new ValidatorFactory())->make(['name' => ''], ['name' => 'required']);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->setData(['name' => 'John'])->passes());
        $this->assertSame(['name' => 'John'], $validator->validated());
        $this->assertTrue($validator->errors()->isEmpty());

        $this->assertFalse($validator->setRules(['name' => 'integer'])->passes());
        $this->assertTrue($validator->setRules(['name' => 'string'])->passes());
        $this->assertSame(['name' => 'John'], $validator->validated());
    }

    public function testAfterCallbackCanMakeValidationFailAndRepeatedValidationIsStable(): void
    {
        $validator = new Validator(['name' => 'John', 'role' => 'admin'], [
            'name' => 'required|string',
            'role' => 'required|string',
        ]);

        $calls = 0;
        $validator->after(function (Validator $validator) use (&$calls): void {
            $calls++;
            $validator->errors()->add('role', 'Role denied.');
        });

        $this->assertFalse($validator->passes());
        $this->assertFalse($validator->passes());
        $this->assertSame(1, $calls);
        $this->assertSame('Role denied.', $validator->errors()->first('role'));
        $this->assertSame(['name' => 'John'], $validator->validated());
    }
}
