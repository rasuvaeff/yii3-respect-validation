<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Tests;

use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Rasuvaeff\Yii3RespectValidation\Tests\Support\StubValidator;
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

    public function getOptionsMapsEnumParametersToTheirNameAndDropsOtherObjects(): void
    {
        $rule = new RespectRule(new StubValidator(parameters: [
            'mode' => StubMode::Strict,
            'object' => new \stdClass(),
            'nothing' => null,
            'flag' => true,
            'ratio' => 1.5,
            'label' => 'x',
        ]));

        Assert::same($rule->getOptions(), [
            'mode' => 'Strict',
            'object' => null,
            'nothing' => null,
            'flag' => true,
            'ratio' => 1.5,
            'label' => 'x',
            'skipOnEmpty' => false,
            'skipOnError' => false,
        ]);
    }

    public function getOptionsSkipsNonStringParameterKeys(): void
    {
        $rule = new RespectRule(new StubValidator(parameters: [0 => 'a', 'k' => 1]));

        Assert::same($rule->getOptions(), ['k' => 1, 'skipOnEmpty' => false, 'skipOnError' => false]);
    }

    public function getOptionsIgnoresNonArrayParameters(): void
    {
        $rule = new RespectRule(new StubValidator(parameters: 'not an array'));

        Assert::same($rule->getOptions(), ['skipOnEmpty' => false, 'skipOnError' => false]);
    }
}

enum StubMode
{
    case Strict;
}
