<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation;

use Stringable;
use Yiisoft\Translator\MessageFormatterInterface;

/**
 * @api
 */
final readonly class RespectMessageFormatter implements MessageFormatterInterface
{
    #[\Override]
    public function format(string $message, array $parameters, string $locale): string
    {
        $replacements = [];
        foreach ($parameters as $name => $value) {
            $replacements['{{' . $name . '}}'] = $this->stringify($value);
        }

        return strtr($message, $replacements);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof Stringable => (string) $value,
            default => '',
        };
    }
}
