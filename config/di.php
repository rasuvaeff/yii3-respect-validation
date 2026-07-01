<?php

declare(strict_types=1);

use Rasuvaeff\Yii3RespectValidation\RespectMessageFormatter;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\IdMessageReader;

/** @var array $params */

return [
    RespectRuleHandler::class => [
        '__construct()' => [
            'translationCategory' => $params['rasuvaeff/yii3-respect-validation']['translation.category'],
        ],
    ],
    'yii3-respect-validation.categorySource' => [
        'definition' => static function () use ($params): CategorySource {
            return new CategorySource(
                $params['rasuvaeff/yii3-respect-validation']['translation.category'],
                new IdMessageReader(),
                new RespectMessageFormatter(),
            );
        },
        'tags' => ['translation.categorySource'],
    ],
];
