<?php

declare(strict_types=1);

use Rasuvaeff\Yii3RespectValidation\RespectMessageFormatter;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\IdMessageReader;
use Yiisoft\Translator\Message\Php\MessageSource;

/** @var array $params */

return [
    RespectRuleHandler::class => [
        '__construct()' => [
            'translationCategory' => $params['rasuvaeff/yii3-respect-validation']['translation.category'],
        ],
    ],
    'yii3-respect-validation.categorySource' => [
        'definition' => static function () use ($params): CategorySource {
            // MessageSource (yiisoft/translator-message-php) unlocks the bundled
            // messages/{locale} catalogs; without it messages stay in English via id-passthrough.
            $reader = class_exists(MessageSource::class)
                ? new MessageSource(dirname(__DIR__) . '/messages')
                : new IdMessageReader();

            return new CategorySource(
                $params['rasuvaeff/yii3-respect-validation']['translation.category'],
                $reader,
                new RespectMessageFormatter(),
            );
        },
        'tags' => ['translation.categorySource'],
    ],
];
