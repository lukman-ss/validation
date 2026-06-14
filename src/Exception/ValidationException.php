<?php

declare(strict_types=1);

namespace Lukman\Validation\Exception;

use Exception;
use Lukman\Validation\MessageBag;

class ValidationException extends Exception
{
    public function __construct(
        private MessageBag $errors,
        string $message = 'Validation failed.'
    ) {
        parent::__construct($message);
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }
}
