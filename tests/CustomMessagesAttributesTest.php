<?php

declare(strict_types=1);

namespace Lukman\Validation\Test;

use Lukman\Validation\ValidatorFactory;
use PHPUnit\Framework\TestCase;

class CustomMessagesAttributesTest extends TestCase
{
    public function testMessagePriorityExactWildcardGlobalDefault(): void
    {
        $factory = new ValidatorFactory();

        $exact = $factory->make(
            ['users' => [['email' => 'bad']]],
            ['users.*.email' => 'email'],
            [
                'email' => 'Global :attribute.',
                'users.*.email.email' => 'Wildcard :attribute.',
                'users.0.email.email' => 'Exact :attribute.',
            ]
        );

        $exact->passes();
        $this->assertSame('Exact users.0.email.', $exact->errors()->first('users.0.email'));

        $wildcard = $factory->make(
            ['users' => [['email' => 'bad']]],
            ['users.*.email' => 'email'],
            [
                'email' => 'Global :attribute.',
                'users.*.email.email' => 'Wildcard :attribute.',
            ]
        );

        $wildcard->passes();
        $this->assertSame('Wildcard users.0.email.', $wildcard->errors()->first('users.0.email'));

        $global = $factory->make(['email' => 'bad'], ['email' => 'email'], ['email' => 'Global :attribute.']);
        $global->passes();
        $this->assertSame('Global email.', $global->errors()->first('email'));

        $default = $factory->make(['email' => 'bad'], ['email' => 'email']);
        $default->passes();
        $this->assertSame('The email must be a valid email address.', $default->errors()->first('email'));
    }

    public function testPlaceholdersAndWildcardAttribute(): void
    {
        $validator = (new ValidatorFactory())->make(
            ['users' => [['age' => 3, 'password' => 'secret', 'password_confirmation' => 'different']]],
            [
                'users.*.age' => 'min:5',
                'users.*.password' => 'confirmed',
            ],
            [
                'min' => ':attribute value :value min :min.',
                'confirmed' => ':attribute must match :other.',
            ],
            [
                'users.*.age' => 'user age',
                'users.*.password_confirmation' => 'password confirmation',
            ]
        );

        $validator->passes();

        $this->assertSame('user age value 3 min 5.', $validator->errors()->first('users.0.age'));
        $this->assertSame('users.0.password must match password confirmation.', $validator->errors()->first('users.0.password'));
    }

    public function testMaxSizeBetweenPlaceholders(): void
    {
        $validator = (new ValidatorFactory())->make(
            ['name' => 'abcdef', 'code' => 'abc', 'title' => 'x'],
            ['name' => 'max:3', 'code' => 'size:4', 'title' => 'between:2,5'],
            [
                'max' => 'Max :max.',
                'size' => 'Size :size.',
                'between' => 'Between :min and :max.',
            ]
        );

        $validator->passes();

        $this->assertSame('Max 3.', $validator->errors()->first('name'));
        $this->assertSame('Size 4.', $validator->errors()->first('code'));
        $this->assertSame('Between 2 and 5.', $validator->errors()->first('title'));
    }
}
