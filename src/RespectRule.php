<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation;

use Attribute;
use Closure;
use ReflectionClass;
use ReflectionProperty;
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
 *
 * @psalm-import-type SkipOnEmptyValue from SkipOnEmptyInterface
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class RespectRule implements DumpedRuleInterface, SkipOnEmptyInterface, SkipOnErrorInterface, WhenInterface
{
    use SkipOnEmptyTrait;
    use SkipOnErrorTrait;
    use WhenTrait;

    private bool $skipOnError;

    private ?Closure $when;

    /**
     * @psalm-param SkipOnEmptyValue $skipOnEmpty
     */
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

    /**
     * @return array<string, bool|int|float|string|null>
     */
    private function getValidatorOptions(): array
    {
        // Many Respect validators (e.g. Between, extending Validators\Core\Envelope) store their
        // constructor arguments in a $parameters property, which is the same array Respect uses
        // to fill in {{placeholder}} template text - reusing it here avoids per-rule mapping.
        // $parameters is private on Envelope itself, so it must be looked up through the parent
        // chain: ReflectionClass::hasProperty() does not see private properties of ancestor classes.
        $property = $this->findParametersProperty(new ReflectionClass($this->validator));

        if ($property === null) {
            return [];
        }

        $parameters = $property->getValue($this->validator);

        if (!is_array($parameters)) {
            return [];
        }

        $options = [];
        foreach (array_keys($parameters) as $name) {
            if (!is_string($name)) {
                continue;
            }

            $options[$name] = $this->normalizeOptionValue($parameters[$name]);
        }

        return $options;
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<T> $reflection
     */
    private function findParametersProperty(ReflectionClass $reflection): ?ReflectionProperty
    {
        if ($reflection->hasProperty('parameters')) {
            return $reflection->getProperty('parameters');
        }

        $parent = $reflection->getParentClass();

        return $parent === false ? null : $this->findParametersProperty($parent);
    }

    private function normalizeOptionValue(mixed $value): bool|int|float|string|null
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        return $value instanceof UnitEnum ? $value->name : null;
    }
}
