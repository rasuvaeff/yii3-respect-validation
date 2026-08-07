<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3RespectValidation\RespectMessageFormatter;
use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Rasuvaeff\Yii3RespectValidation\RespectRuleHandler;
use Respect\Validation\Result as RespectResult;
use Respect\Validation\Validator as RespectValidator;
use Respect\Validation\Validators\AllOf;
use Respect\Validation\Validators\Between;
use Respect\Validation\Validators\BoolType;
use Respect\Validation\Validators\Each;
use Respect\Validation\Validators\IntType;
use Respect\Validation\Validators\Length;
use Respect\Validation\Validators\Not;
use Respect\Validation\Validators\StringType;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\IdMessageReader;
use Yiisoft\Translator\Message\Php\MessageSource;
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
        $rule = new RespectRule(new AllOf(new StringType(), new Length(new Between(1, 5))));

        $result = $this->handler->validate(123, $rule, new ValidationContext());

        Assert::false($result->isValid());
        Assert::same($result->getErrorMessages(), ['Value must be a string', 'Value must be countable or a string']);
    }

    public function chainedWrapperSkipsItsOwnFragmentTemplate(): void
    {
        $rule = new RespectRule(new Length(new Between(1, 5)));

        $result = $this->handler->validate('abcdef', $rule, new ValidationContext());

        Assert::false($result->isValid());
        Assert::same($result->getErrorMessages(), ['Value must be between 1 and 5']);
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

    public function shippedRussianCatalogRendersTranslatedMessage(): void
    {
        $translator = new Translator(locale: 'ru');
        $translator->addCategorySources(new CategorySource(
            'yii3-respect-validation',
            new MessageSource(dirname(__DIR__) . '/messages'),
            new RespectMessageFormatter(),
        ));

        $handler = new RespectRuleHandler(translator: $translator, translationCategory: 'yii3-respect-validation');

        $context = (new ValidationContext())->setPropertyLabel('username');
        $result = $handler->validate(123, new RespectRule(new StringType()), $context);

        Assert::false($result->isValid());
        Assert::same($result->getErrorMessages(), ['Значение «Username» должно быть строкой']);
    }

    public function throwsOnUnexpectedRule(): void
    {
        Expect::exception(UnexpectedRuleException::class);

        $this->handler->validate('value', new FakeRule(), new ValidationContext());
    }

    #[Property(runs: 200)]
    public function adapterMatchesRespectOutcome(mixed $value, int $validatorIndex): void
    {
        $validator = (self::validatorPool()[$validatorIndex])();
        $rule = new RespectRule($validator);

        $result = $this->handler->validate($value, $rule, new ValidationContext());

        Assert::same($result->isValid(), $validator->evaluate($value)->hasPassed);
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    public static function adapterMatchesRespectOutcomeGenerators(): array
    {
        return [
            'value' => self::valueGenerator(),
            'validatorIndex' => Gen::intBetween(0, count(self::validatorPool()) - 1),
        ];
    }

    #[Property(runs: 200)]
    public function collectFailuresMatchesFlatAllOfLeafFailures(mixed $value, array $leafIndices): void
    {
        $leaves = array_map(
            static fn(int $index): RespectValidator => (self::leafValidatorPool()[$index])(),
            $leafIndices,
        );
        $rule = new RespectRule(new AllOf(...$leaves));

        $result = $this->handler->validate($value, $rule, new ValidationContext());

        $expectedFailures = 0;
        foreach ($leaves as $leaf) {
            if (!$leaf->evaluate($value)->hasPassed) {
                $expectedFailures++;
            }
        }

        Assert::same(count($result->getErrors()), $expectedFailures);

        foreach ($result->getErrorMessages() as $message) {
            Assert::true($message !== '');
        }
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    public static function collectFailuresMatchesFlatAllOfLeafFailuresGenerators(): array
    {
        return [
            'value' => self::valueGenerator(),
            'leafIndices' => Gen::arrayOf(Gen::intBetween(0, count(self::leafValidatorPool()) - 1), 2, 4),
        ];
    }

    #[Property(runs: 200)]
    public function collectFailuresCountIsBoundedByAdjacencyChainDepth(mixed $value, int $validatorIndex): void
    {
        $validator = (self::adjacencyValidatorPool()[$validatorIndex])();
        $rule = new RespectRule($validator);

        $result = $this->handler->validate($value, $rule, new ValidationContext());
        $respectResult = $validator->evaluate($value);

        $actualFailures = count($result->getErrors());
        $minPossibleFailures = self::countChainEndFailures($respectResult);
        $maxPossibleFailures = self::countAllFailingTerminalNodes($respectResult);

        Assert::true($actualFailures >= $minPossibleFailures);
        Assert::true($actualFailures <= $maxPossibleFailures);

        foreach ($result->getErrorMessages() as $message) {
            Assert::true($message !== '');
        }
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    public static function collectFailuresCountIsBoundedByAdjacencyChainDepthGenerators(): array
    {
        return [
            'value' => self::valueGenerator(),
            'validatorIndex' => Gen::intBetween(0, count(self::adjacencyValidatorPool()) - 1),
        ];
    }

    #[Property(runs: 200)]
    public function eachValuePathMatchesFailingKeys(array $items): void
    {
        $rule = new RespectRule(new Each(new IntType()));

        $result = $this->handler->validate($items, $rule, new ValidationContext());

        $expectedFailingKeys = [];
        foreach ($items as $key => $item) {
            if (!is_int($item)) {
                $expectedFailingKeys[] = $key;
            }
        }

        $actualKeys = [];
        foreach ($result->getErrors() as $error) {
            $actualKeys[] = $error->getValuePath()[0] ?? null;
        }

        sort($expectedFailingKeys);
        sort($actualKeys);

        Assert::same($actualKeys, $expectedFailingKeys);
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    public static function eachValuePathMatchesFailingKeysGenerators(): array
    {
        return [
            'items' => Gen::dictOf(
                Gen::stringFrom('abcdefgh', 1, 3),
                Gen::oneOf(1, 2, 'x', 'y', true, null),
                0,
                6,
            ),
        ];
    }

    /**
     * Independent lower bound for {@see RespectRuleHandler}'s error count: follows the same
     * `children`/`adjacent` structural links `collectFailures()` walks, but - unlike it - never
     * inspects {@see RespectResult::hasCustomTemplate()}, so it cannot surface a chain wrapper's
     * own fragment message. It always resolves an adjacency chain down to its final link.
     */
    private static function countChainEndFailures(RespectResult $node): int
    {
        if ($node->hasPassed) {
            return 0;
        }

        if ($node->children !== []) {
            $count = 0;
            foreach ($node->children as $child) {
                $count += self::countChainEndFailures($child);
            }

            return $count;
        }

        if ($node->adjacent instanceof RespectResult) {
            return self::countChainEndFailures($node->adjacent);
        }

        return 1;
    }

    /**
     * Independent upper bound for {@see RespectRuleHandler}'s error count: counts every failing
     * node with no `children` along the way, including every link of an adjacency chain, as if
     * `hasCustomTemplate()` were always true.
     */
    private static function countAllFailingTerminalNodes(RespectResult $node): int
    {
        if ($node->hasPassed) {
            return 0;
        }

        if ($node->children !== []) {
            $count = 0;
            foreach ($node->children as $child) {
                $count += self::countAllFailingTerminalNodes($child);
            }

            return $count;
        }

        $count = 1;
        if ($node->adjacent instanceof RespectResult) {
            $count += self::countAllFailingTerminalNodes($node->adjacent);
        }

        return $count;
    }

    /**
     * @return list<Closure(): RespectValidator>
     */
    private static function adjacencyValidatorPool(): array
    {
        return [
            static fn(): RespectValidator => new Length(new Between(1, 5)),
            static fn(): RespectValidator => new AllOf(new StringType(), new Length(new Between(1, 5))),
            static fn(): RespectValidator => new AllOf(new Length(new Between(1, 5)), new Each(new IntType())),
            static fn(): RespectValidator => new Each(new Length(new Between(1, 3))),
        ];
    }

    private static function valueGenerator(): ArbitraryInterface
    {
        return Gen::frequency([
            [2, Gen::string()],
            [2, Gen::int()],
            [1, Gen::bool()],
            [1, Gen::constant(null)],
            [1, Gen::arrayOf(Gen::int(), 0, 4)],
            [1, Gen::dictOf(Gen::stringFrom('abcdefgh', 1, 3), Gen::oneOf(1, 'x', true), 0, 4)],
        ]);
    }

    /**
     * @return list<Closure(): RespectValidator>
     */
    private static function validatorPool(): array
    {
        return [
            static fn(): RespectValidator => new StringType(),
            static fn(): RespectValidator => new IntType(),
            static fn(): RespectValidator => new BoolType(),
            static fn(): RespectValidator => new Length(new Between(1, 5)),
            static fn(): RespectValidator => new AllOf(new StringType(), new Length(new Between(1, 5))),
            static fn(): RespectValidator => new Each(new IntType()),
            static fn(): RespectValidator => new Not(new StringType()),
        ];
    }

    /**
     * @return list<Closure(): RespectValidator>
     */
    private static function leafValidatorPool(): array
    {
        return [
            static fn(): RespectValidator => new StringType(),
            static fn(): RespectValidator => new IntType(),
            static fn(): RespectValidator => new BoolType(),
        ];
    }
}
