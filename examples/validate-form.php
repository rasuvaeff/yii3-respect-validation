<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Respect\Validation\Validators\AllOf;
use Respect\Validation\Validators\Length;
use Respect\Validation\Validators\StringType;
use Yiisoft\Validator\Validator;

final class RegisterForm
{
    public function __construct(
        #[RespectRule(new AllOf(new StringType(), new Length(1, 20)))]
        public string $username = '',
    ) {}
}

$result = (new Validator())->validate(new RegisterForm(username: str_repeat('a', 30)));

var_dump($result->isValid());
var_dump($result->getPropertyErrorMessages('username'));
