<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation;

use Attribute;
use Closure;
use ReflectionClass;
use Respect\Validation\Validator as RespectValidator;
use Respect\Validation\ValidatorBuilder;
use UnitEnum;
use Yiisoft\Validator\DumpedRuleInterface;
use Yiisoft\Validator\Rule\Trait\SkipOnEmptyTrait;
use Yiisoft\Validator\Rule\Trait\SkipOnErrorTrait;
use Yiisoft\Validator\Rule\Trait\WhenTrait;
use Yiisoft\Validator\RuleHandlerInterface;
use Yiisoft\Validator\SkipOnEmptyInterface;
use Yiisoft\Validator\SkipOnErrorInterface;
use Yiisoft\Validator\WhenInterface;

/**
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class RespectRule implements DumpedRuleInterface, SkipOnEmptyInterface, SkipOnErrorInterface, WhenInterface
{
    use SkipOnEmptyTrait;
    use SkipOnErrorTrait;
    use WhenTrait;

    public function __construct(
        private readonly RespectValidator|ValidatorBuilder $validator,
        bool|callable|null $skipOnEmpty = null,
        bool $skipOnError = false,
        ?Closure $when = null,
    ) {
        $this->skipOnEmpty = $skipOnEmpty;
        $this->skipOnError = $skipOnError;
        $this->when = $when;
    }

    public function getValidator(): RespectValidator|ValidatorBuilder
    {
        return $this->validator;
    }

    #[\Override]
    public function getHandler(): string|RuleHandlerInterface
    {
        return RespectRuleHandler::class;
    }

    #[\Override]
    public function getName(): string
    {
        return 'respect-' . strtolower((new ReflectionClass($this->validator))->getShortName());
    }

    #[\Override]
    public function getOptions(): array
    {
        return [
            ...$this->getValidatorOptions(),
            'skipOnEmpty' => $this->getSkipOnEmptyOption(),
            'skipOnError' => $this->skipOnError,
        ];
    }

    private function getValidatorOptions(): array
    {
        // Many Respect validators (e.g. Between, extending Validators\Core\Envelope) store their
        // constructor arguments in a $parameters property, which is the same array Respect uses
        // to fill in {{placeholder}} template text - reusing it here avoids per-rule mapping.
        $reflection = new ReflectionClass($this->validator);

        if (!$reflection->hasProperty('parameters')) {
            return [];
        }

        $parameters = $reflection->getProperty('parameters')->getValue($this->validator);

        if (!is_array($parameters)) {
            return [];
        }

        $options = [];
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || !$this->isSerializable($value)) {
                continue;
            }

            $options[$name] = $value instanceof UnitEnum ? $value->name : $value;
        }

        return $options;
    }

    private function isSerializable(mixed $value): bool
    {
        if ($value === null || is_scalar($value) || $value instanceof UnitEnum) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($item !== null && !is_scalar($item)) {
                return false;
            }
        }

        return true;
    }
}
