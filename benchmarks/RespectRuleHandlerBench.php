<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Benchmarks;

use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Respect\Validation\Validators\AllOf;
use Respect\Validation\Validators\Alnum;
use Respect\Validation\Validators\Between;
use Respect\Validation\Validators\Length;
use Respect\Validation\Validators\StringType;
use Testo\Bench;
use Yiisoft\Validator\ValidationContext;

final class RespectRuleHandlerBench
{
    #[Bench(
        callables: [
            'deep-chain' => [self::class, 'validateDeepChain'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function validateSingleRule(): void
    {
        $handler = new RespectRuleHandler();
        $rule = new RespectRule(new StringType());

        $handler->validate('some value', $rule, new ValidationContext());
    }

    public static function validateDeepChain(): void
    {
        $handler = new RespectRuleHandler();
        $rule = new RespectRule(
            new AllOf(new StringType(), new Length(new Between(1, 50)), new Alnum()),
        );

        $handler->validate('some value', $rule, new ValidationContext());
    }
}
