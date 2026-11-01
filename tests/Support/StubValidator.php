<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3RespectValidation\Tests\Support;

use Respect\Validation\Id;
use Respect\Validation\Path;
use Respect\Validation\Result;
use Respect\Validation\Validator;

/**
 * @internal
 */
final class StubValidator implements Validator
{
    /**
     * @param array<array-key, mixed> $resultParameters
     */
    public function __construct(
        public mixed $parameters = [],
        private readonly array $resultParameters = [],
        private readonly string $template = Validator::TEMPLATE_STANDARD,
        private readonly ?Path $path = null,
    ) {}

    #[\Override]
    public function evaluate(mixed $input): Result
    {
        return new Result(
            hasPassed: false,
            input: $input,
            validator: $this,
            id: Id::fromValidator($this),
            parameters: $this->resultParameters,
            template: $this->template,
            path: $this->path,
        );
    }
}
