# Lukman Validation

A lightweight standalone PHP validation library with zero external runtime dependencies.

## Requirements

- PHP 8.2 or higher

## Installation

Install the package via Composer:

```bash
composer require lukman-ss/validation
```

## Basic Usage

Run validation using either standard boolean checks or the exception flow.

```php
use Lukman\Validation\Validator;

$data = [
    'username' => 'john_doe',
    'age' => 25,
];

$rules = [
    'username' => 'required|string|min:3',
    'age' => 'required|integer',
];

$validator = new Validator($data, $rules);

if ($validator->passes()) {
    // Get all validated data
    $validated = $validator->validated();
} else {
    // Get validation errors as a MessageBag
    $errors = $validator->errors();
    echo $errors->first('username');
}
```

## Exception Flow

Use `validate()` (or `validateOrFail()`) to directly get validated data or throw a `ValidationException` when validation fails.

```php
use Lukman\Validation\Validator;
use Lukman\Validation\Exception\ValidationException;

$validator = new Validator($data, $rules);

try {
    $validated = $validator->validate(); // or validateOrFail()
} catch (ValidationException $e) {
    $errors = $e->errors(); // MessageBag
    print_r($errors->toArray());
}
```

To validate without throwing exceptions, use the `safe()` helper:

```php
$validated = $validator->safe(); // Returns validated fields only, without throwing
```

## Rules Formatting

### String Rules
Separate multiple rules with a pipe `|` character. Rule parameters are separated by commas `,`.

```php
$rules = [
    'email' => 'required|string|email',
    'age' => 'required|integer|between:18,99',
];
```

### Array Rules
Rules can also be provided as an array, enabling the use of rule objects and closures.

```php
$rules = [
    'email' => ['required', 'string', 'email'],
    'status' => [
        'required',
        function (string $attribute, mixed $value, array $data): bool {
            return in_array($value, ['active', 'inactive'], true);
        },
    ],
];
```

## Nested Data

Validate deep nested arrays using dot notation:

```php
$data = [
    'user' => [
        'profile' => [
            'name' => 'Lukman',
        ],
    ],
];

$rules = [
    'user.profile.name' => 'required|string|min:2',
];

$validator = new Validator($data, $rules);
$validated = $validator->validate();
// Returns: ['user' => ['profile' => ['name' => 'Lukman']]]
```

## Wildcard Validation

Validate all elements within an array using the wildcard `*` operator. Error keys will automatically map to the specific indices:

```php
$data = [
    'items' => [
        ['qty' => 5],
        ['qty' => 'not-a-number'],
    ],
];

$rules = [
    'items.*.qty' => 'required|integer',
];

$validator = new Validator($data, $rules);
$validator->passes(); // false

$errors = $validator->errors();
echo $errors->first('items.1.qty'); // "The items.1.qty must be an integer."
```

## Custom Messages

Define custom messages globally per rule or specifically for a field:

```php
$messages = [
    'required' => 'The :attribute field is mandatory.',
    'email.required' => 'Please provide your email address.',
    'items.*.qty.integer' => 'Quantity must be a valid number.',
];

$validator = new Validator($data, $rules, $messages);
```

### Placeholders
Custom messages support the following placeholder values:
- `:attribute`: The human-readable name of the validated field.
- `:value`: The value of the field being validated.
- `:min`: Parameter used in `min` or `between` rules.
- `:max`: Parameter used in `max` or `between` rules.
- `:size`: Parameter used in the `size` rule.
- `:other`: Name of the compared field in `same` or `different` rules.

## Custom Attributes

Translate technical field paths into user-friendly names:

```php
$attributes = [
    'email' => 'email address',
    'items.*.qty' => 'item quantity',
];

$validator = new Validator($data, $rules, [], $attributes);
```

## Extensibility & Custom Rules

### Custom Rule Objects (RuleInterface)
Create a class implementing `RuleInterface`:

```php
use Lukman\Validation\RuleInterface;

class UppercaseRule implements RuleInterface
{
    public function passes(string $attribute, mixed $value, array $data): bool
    {
        return is_string($value) && strtoupper($value) === $value;
    }

    public function message(string $attribute): string
    {
        return 'The :attribute must be in uppercase letters.';
    }
}
```

Use it in your rules array:

```php
$rules = [
    'code' => ['required', new UppercaseRule()],
];
```

### Closure Rules
Define quick rules inline using Closures:

```php
$rules = [
    'username' => [
        'required',
        function (string $attribute, mixed $value, array $data): bool|string {
            if ($value === 'admin') {
                return 'You cannot choose "admin" as your username.';
            }
            return true; // Return true if valid
        }
    ]
];
```

### Global Factory Extensions
Register custom rules globally to use them with string rule definitions:

```php
use Lukman\Validation\ValidatorFactory;

ValidatorFactory::extend('strong_password', function (string $attribute, mixed $value, array $parameters, array $data): bool {
    return is_string($value) && strlen($value) >= 8 && preg_match('/[A-Z]/', $value);
}, 'The :attribute must be at least 8 characters long and contain an uppercase letter.');

// Now use it like any standard rule string:
$rules = [
    'password' => 'required|strong_password',
];
```

## Post-Validation Hooks (after)

Attach callbacks that execute after standard rule evaluations. This is useful for complex, multi-field, or conditional validation checks:

```php
$validator = new Validator($data, $rules);

$validator->after(function (Validator $v) {
    if ($v->validated()['password'] === $v->validated()['username']) {
        $v->errors()->add('password', 'Password cannot be the same as username.');
    }
});
```

## Runtime Mutation

Change the dataset or rule configurations dynamically using setters. Setting new data or rules automatically clears existing errors and resets the validation state:

```php
$validator = new Validator($data, $rules);
$validator->passes(); // Runs validation

// Change data and validate again
$validator->setData($newData);
$validator->passes(); // Re-runs validation on new data

// Change rules and validate again
$validator->setRules($newRules);
$validator->passes(); // Re-runs validation on new rules
```

## License

This package is open-source software licensed under the [MIT License](LICENSE).
