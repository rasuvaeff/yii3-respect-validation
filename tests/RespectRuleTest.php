<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Tests;

use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Respect\Validation\Validators\Between;
use Respect\Validation\Validators\StringType;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(RespectRule::class)]
final class RespectRuleTest
{
    public function getHandlerReturnsHandlerClassName(): void
    {
        Assert::same((new RespectRule(new StringType()))->getHandler(), RespectRuleHandler::class);
    }

    public function getNameIsDerivedFromWrappedValidatorClass(): void
    {
        Assert::same((new RespectRule(new StringType()))->getName(), 'respect-stringtype');
    }

    public function getValidatorReturnsWrappedInstance(): void
    {
        $validator = new StringType();

        Assert::same((new RespectRule($validator))->getValidator(), $validator);
    }

    public function getOptionsContainsOnlySkipDefaultsForRuleWithoutParameters(): void
    {
        Assert::same(
            (new RespectRule(new StringType()))->getOptions(),
            ['skipOnEmpty' => false, 'skipOnError' => false],
        );
    }

    public function getOptionsContainsParametersForEnvelopeBasedRule(): void
    {
        Assert::same(
            (new RespectRule(new Between(1, 10)))->getOptions(),
            ['minValue' => 1, 'maxValue' => 10, 'skipOnEmpty' => false, 'skipOnError' => false],
        );
    }
}
