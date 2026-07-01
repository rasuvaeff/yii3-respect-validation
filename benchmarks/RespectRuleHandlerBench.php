<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Benchmarks;

use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Testo\Bench;
use v;
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
        $rule = new RespectRule(v::stringType());

        $handler->validate('some value', $rule, new ValidationContext());
    }

    public static function validateDeepChain(): void
    {
        $handler = new RespectRuleHandler();
        $rule = new RespectRule(
            v::stringType()->length(1, 50)->alnum()->notEmpty(),
        );

        $handler->validate('some value', $rule, new ValidationContext());
    }
}
