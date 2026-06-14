<?php

declare(strict_types=1);

namespace Lukman\Validation\Test;

use Lukman\Validation\Validator;
use Lukman\Validation\ValidatorFactory;
use Lukman\Validation\MessageBag;
use Lukman\Validation\ParsedRule;
use Lukman\Validation\RuleInterface;
use Lukman\Validation\RuleParser;
use Lukman\Validation\ValidationResult;
use Lukman\Validation\Exception\ValidationRuleException;
use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    public function testValidatorIsAutoloaded(): void
    {
        $this->assertTrue(class_exists(Validator::class));
        $this->assertTrue(class_exists(ValidatorFactory::class));
    }

    public function testPhaseTwoClassesAreAutoloaded(): void
    {
        $this->assertTrue(class_exists(MessageBag::class));
        $this->assertTrue(class_exists(ValidationResult::class));
    }

    public function testPhaseThreeClassesAreAutoloaded(): void
    {
        $this->assertTrue(class_exists(RuleParser::class));
        $this->assertTrue(class_exists(ParsedRule::class));
        $this->assertTrue(interface_exists(RuleInterface::class));
        $this->assertTrue(class_exists(ValidationRuleException::class));
    }
}
