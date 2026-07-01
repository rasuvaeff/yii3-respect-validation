<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Tests;

use Rasuvaeff\Yii3RespectValidation\RespectMessageFormatter;
use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Respect\Validation\Validators\AllOf;
use Respect\Validation\Validators\Each;
use Respect\Validation\Validators\IntType;
use Respect\Validation\Validators\Length;
use Respect\Validation\Validators\StringType;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\IdMessageReader;
use Yiisoft\Translator\Translator;
use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\ValidationContext;

#[Test]
#[Covers(RespectRuleHandler::class)]
final class RespectRuleHandlerTest
{
    private RespectRuleHandler $handler;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->handler = new RespectRuleHandler();
    }

    public function validValuePasses(): void
    {
        $result = $this->handler->validate('abc', new RespectRule(new StringType()), new ValidationContext());

        Assert::true($result->isValid());
    }

    public function invalidValueFails(): void
    {
        $result = $this->handler->validate(123, new RespectRule(new StringType()), new ValidationContext());

        Assert::false($result->isValid());
        Assert::same($result->getErrorMessages(), ['Value must be a string']);
    }

    public function usesPropertyLabelAsSubject(): void
    {
        $context = (new ValidationContext())->setPropertyLabel('username');

        $result = $this->handler->validate(123, new RespectRule(new StringType()), $context);

        Assert::same($result->getErrorMessages(), ['Username must be a string']);
    }

    public function allOfCollectsOneMessagePerFailedChild(): void
    {
        $rule = new RespectRule(new AllOf(new StringType(), new Length(1, 5)));

        $result = $this->handler->validate(123, $rule, new ValidationContext());

        Assert::false($result->isValid());
        Assert::same($result->getErrorMessages(), ['Value must be a string']);
    }

    public function eachAttachesValuePathFromRespect(): void
    {
        $rule = new RespectRule(new Each(new IntType()));

        $result = $this->handler->validate(['a' => 1, 'b' => 'x'], $rule, new ValidationContext());

        Assert::false($result->isValid());
        Assert::same($result->getErrors()[0]->getValuePath(), ['b']);
    }

    public function translatorRendersViaCustomCategory(): void
    {
        $translator = new Translator(locale: 'en');
        $translator->addCategorySources(new CategorySource(
            'yii3-respect-validation',
            new IdMessageReader(),
            new RespectMessageFormatter(),
        ));

        $handler = new RespectRuleHandler(translator: $translator, translationCategory: 'yii3-respect-validation');

        $result = $handler->validate(123, new RespectRule(new StringType()), new ValidationContext());

        Assert::false($result->isValid());
        Assert::same($result->getErrorMessages(), ['Value must be a string']);
    }

    public function throwsOnUnexpectedRule(): void
    {
        Expect::exception(UnexpectedRuleException::class);

        $this->handler->validate('value', new FakeRule(), new ValidationContext());
    }
}
