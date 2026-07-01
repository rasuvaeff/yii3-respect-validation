<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation;

use InvalidArgumentException;
use Respect\Validation\Message\TemplateRegistry;
use Respect\Validation\Result as RespectResult;
use Stringable;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\RuleHandlerInterface;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\ValidationContext;

/**
 * @api
 */
final readonly class RespectRuleHandler implements RuleHandlerInterface
{
    public function __construct(
        private ?TranslatorInterface $translator = null,
        private string $translationCategory = 'yii3-respect-validation',
        private TemplateRegistry $templateRegistry = new TemplateRegistry(),
        private RespectMessageFormatter $fallbackFormatter = new RespectMessageFormatter(),
    ) {}

    #[\Override]
    public function validate(mixed $value, RuleInterface $rule, ValidationContext $context): Result
    {
        if (!$rule instanceof RespectRule) {
            throw new UnexpectedRuleException(RespectRule::class, $rule);
        }

        $result = new Result();

        foreach ($this->collectFailures($rule->getValidator()->evaluate($value)) as $failure) {
            $parameters = $this->normalizeParameters([
                ...$failure->parameters,
                'subject' => $context->getCapitalizedTranslatedProperty(),
                'input' => $failure->input,
            ]);

            $result->addErrorWithoutPostProcessing(
                $this->renderMessage($failure, $parameters),
                $parameters,
                $this->buildValuePath($failure),
            );
        }

        return $result;
    }

    /**
     * @return iterable<RespectResult>
     */
    private function collectFailures(RespectResult $node): iterable
    {
        if ($node->hasPassed) {
            return;
        }

        if ($node->children !== []) {
            foreach ($node->children as $child) {
                yield from $this->collectFailures($child);
            }

            return;
        }

        if ($node->adjacent === null) {
            yield $node;

            return;
        }

        // A non-custom template (e.g. Length's own "The length of" wrapper text) is a sentence
        // fragment meant to precede the adjacent result's message, not stand on its own - skip it
        // and surface only the adjacent (more specific) failure instead.
        if ($node->hasCustomTemplate()) {
            yield $node;
        }

        yield from $this->collectFailures($node->adjacent);
    }

    /**
     * @return list<int|string>
     */
    private function buildValuePath(RespectResult $node): array
    {
        $path = [];
        $current = $node->path;
        while ($current !== null) {
            array_unshift($path, $current->value);
            $current = $current->parent;
        }

        return $path;
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @return array<string, bool|int|float|string|null>
     */
    private function normalizeParameters(array $parameters): array
    {
        $normalized = [];
        foreach (array_keys($parameters) as $name) {
            if (!is_string($name)) {
                continue;
            }

            $normalized[$name] = $this->normalizeParameterValue($parameters[$name]);
        }

        return $normalized;
    }

    private function normalizeParameterValue(mixed $value): bool|int|float|string|null
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof Stringable => (string) $value,
            default => null,
        };
    }

    /**
     * @param array<string, bool|int|float|string|null> $parameters
     */
    private function renderMessage(RespectResult $node, array $parameters): string
    {
        $template = $this->resolveTemplate($node);

        if ($this->translator === null) {
            return $this->fallbackFormatter->format($template, $parameters, 'en');
        }

        return $this->translator->translate($template, $parameters, $this->translationCategory);
    }

    private function resolveTemplate(RespectResult $node): string
    {
        try {
            $template = $this->templateRegistry->get($node->validator::class, $node->template);
        } catch (InvalidArgumentException) {
            return $node->template;
        }

        return $node->hasInvertedMode ? $template->inverted : $template->default;
    }
}
