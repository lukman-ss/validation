<?php

declare(strict_types=1);

namespace Lukman\Validation\Test;

use Lukman\Validation\ValidatorFactory;
use PHPUnit\Framework\TestCase;

class NestedWildcardTest extends TestCase
{
    public function testDotNotationValidatedData(): void
    {
        $validator = (new ValidatorFactory())->make(
            ['user' => ['profile' => ['name' => 'John', 'age' => 'bad']]],
            ['user.profile.name' => 'required|string', 'user.profile.age' => 'integer']
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(['user' => ['profile' => ['name' => 'John']]], $validator->validated());
        $this->assertSame('The user.profile.age must be an integer.', $validator->errors()->first('user.profile.age'));
    }

    public function testWildcardUsesOriginalIndexForErrorsAndValidatedData(): void
    {
        $validator = (new ValidatorFactory())->make(
            [
                'users' => [
                    2 => ['email' => 'valid@example.com', 'name' => 'A'],
                    5 => ['email' => 'invalid-email', 'name' => 'B'],
                ],
            ],
            ['users.*.email' => 'required|email']
        );

        $this->assertFalse($validator->passes());
        $this->assertSame('The users.5.email must be a valid email address.', $validator->errors()->first('users.5.email'));
        $this->assertSame(['users' => [2 => ['email' => 'valid@example.com']]], $validator->validated());
    }

    public function testMissingWildcardParentWithoutRequiredIsSkipped(): void
    {
        $validator = (new ValidatorFactory())->make([], ['users.*.email' => 'email']);

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->validated());
    }

    public function testWildcardParentNonArrayFailsClearly(): void
    {
        $validator = (new ValidatorFactory())->make(['users' => 'not-array'], ['users.*.email' => 'required|email']);

        $this->assertFalse($validator->passes());
        $this->assertSame('The users must be an array.', $validator->errors()->first('users.*.email'));
    }
}
