<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation;

use InvalidArgumentException;
use Respect\Validation\Message\TemplateRegistry;
use Respect\Validation\Result as RespectResult;
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
            $result->addErrorWithoutPostProcessing(
                $this->renderMessage($failure, $context),
                $failure->parameters,
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

        if ($node->children === []) {
            yield $node;
        } else {
            foreach ($node->children as $child) {
                yield from $this->collectFailures($child);
            }
        }

        if ($node->adjacent !== null) {
            yield from $this->collectFailures($node->adjacent);
        }
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

    private function renderMessage(RespectResult $node, ValidationContext $context): string
    {
        $template = $this->resolveTemplate($node);
        $parameters = [
            ...$node->parameters,
            'subject' => $context->getCapitalizedTranslatedProperty(),
            'input' => $node->input,
        ];

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
