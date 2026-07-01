<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Tests;

use Rasuvaeff\Yii3RespectValidation\RespectMessageFormatter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(RespectMessageFormatter::class)]
final class RespectMessageFormatterTest
{
    public function substitutesPlaceholders(): void
    {
        $formatter = new RespectMessageFormatter();

        $message = $formatter->format(
            '{{subject}} must be between {{minValue}} and {{maxValue}}',
            ['subject' => 'Age', 'minValue' => 1, 'maxValue' => 10],
            'en',
        );

        Assert::same($message, 'Age must be between 1 and 10');
    }

    public function leavesUnknownPlaceholdersUntouched(): void
    {
        $formatter = new RespectMessageFormatter();

        Assert::same($formatter->format('{{subject}} is invalid', [], 'en'), '{{subject}} is invalid');
    }

    #[DataProvider('scalarValueProvider')]
    public function stringifiesValues(mixed $value, string $expected): void
    {
        $formatter = new RespectMessageFormatter();

        Assert::same($formatter->format('{{value}}', ['value' => $value], 'en'), $expected);
    }

    public static function scalarValueProvider(): iterable
    {
        yield 'null' => [null, ''];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'string' => ['abc', 'abc'];
    }
}
