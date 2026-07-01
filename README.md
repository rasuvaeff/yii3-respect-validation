# rasuvaeff/yii3-respect-validation

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-respect-validation?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-respect-validation)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-respect-validation/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-respect-validation/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-respect-validation/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-respect-validation/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-respect-validation/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-respect-validation/php)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-respect-validation)](LICENSE.md)

Bridge exposing [Respect/Validation](https://github.com/Respect/Validation) rules as
native `yiisoft/validator` rules — usable as a `#[RespectRule]` attribute on a
`FormModel`/DTO property, alongside built-in Yii3 rules, with results going through
the same `Result`/`Yiisoft\Translator` pipeline.

> **Using an AI coding assistant?** [llms.txt](llms.txt) contains a compact
> API reference you can share with the model. Contributors: see [AGENTS.md](AGENTS.md).

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | `>=8.5` (required by `respect/validation` ^3.1) |
| `respect/validation` | `^3.1` |
| `yiisoft/validator` | `^2.6` |
| `yiisoft/translator` | `^3.0` |

## Installation

```bash
composer require rasuvaeff/yii3-respect-validation
```

## Usage

```php
use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Respect\Validation\Validators\AllOf;
use Respect\Validation\Validators\Length;
use Respect\Validation\Validators\StringType;

final class RegisterForm
{
    #[RespectRule(new AllOf(new StringType(), new Length(1, 50)))]
    public string $username = '';
}
```

```php
use Yiisoft\Validator\Validator;

$result = (new Validator())->validate(new RegisterForm());

$result->isValid();                          // false
$result->getPropertyErrorMessages('username'); // ['Username must contain at most 50 characters']
```

`v::` fluent chains work too, since `RespectRule` accepts either a
`Respect\Validation\Validator` instance or a `Respect\Validation\ValidatorBuilder`
(what `v::` returns):

```php
#[RespectRule(new AllOf(new StringType(), new Length(1, 50)))]
public string $username = '';

// equivalent, built at runtime instead of in the attribute:
$rule = new RespectRule(v::stringType()->length(1, 50));
```

> PHP attribute arguments must be constant expressions, so only rule chains built
> from `new Validator(...)` calls work directly in `#[RespectRule(...)]`. `v::`
> chains need to be built outside the attribute and passed to the constructor.

### Skip / conditional validation

`RespectRule` implements the same `SkipOnEmptyInterface` / `SkipOnErrorInterface` /
`WhenInterface` contracts as built-in rules:

```php
#[RespectRule(new StringType(), skipOnEmpty: true, skipOnError: true)]
public string $bio = '';
```

### Messages and translation

Respect's own message templates (e.g. `{{subject}} must be a string`) are rendered
by the handler itself — not by Respect's internal `InterpolationRenderer` (which
needs `symfony/translation-contracts`) — so the whole app keeps a single i18n
pipeline: `Yiisoft\Translator`.

By default (no `TranslatorInterface` wired), messages are rendered in English via
`RespectMessageFormatter`. To translate them through your app's translator, wire
the category via `config-plugin` (already shipped by this package, see
[DI configuration](#di-configuration-yii3)) and add your own catalog:

```php
// messages/ru/yii3-respect-validation.php
return [
    '{{subject}} must be a string' => '{{subject}} должно быть строкой',
];
```

### DI configuration (Yii3)

This package ships `config/di.php` + `config/params.php` via `config-plugin` —
installing it alongside `yiisoft/config` is enough to get `RespectRuleHandler`
wired with a dedicated translation category (`yii3-respect-validation`) using a
`{{placeholder}}`-aware `RespectMessageFormatter` (Respect templates use double
braces, not the ICU syntax the rest of `yiisoft/validator` uses — mixing the two
under one formatter would break placeholder substitution for one of them).

Override the category name in your application config if needed:

```php
// config/params.php
return [
    'rasuvaeff/yii3-respect-validation' => [
        'translation.category' => 'yii3-respect-validation',
    ],
];
```

## Components

### `RespectRule`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `validator` | `Respect\Validation\Validator\|Respect\Validation\ValidatorBuilder` | — | The wrapped Respect rule/chain. |
| `skipOnEmpty` | `bool\|callable\|null` | `null` | Skip on empty, see `SkipOnEmptyInterface`. |
| `skipOnError` | `bool` | `false` | Skip on prior error, see `SkipOnErrorInterface`. |
| `when` | `?Closure` | `null` | Conditional execution, see `WhenInterface`. |

`getOptions()` (for frontend/client-side metadata export, per
`DumpedRuleInterface`) returns the wrapped validator's own `$parameters` property
when present (e.g. `Between` exposes `minValue`/`maxValue` — the same values
Respect uses to fill in its own templates), plus `skipOnEmpty`/`skipOnError`.
**No JavaScript runtime is shipped** — Respect has ~150 rules, many encoding
arbitrary PHP logic (Luhn checksums, locale-aware checks, `Callback` closures);
porting all of them to JS is not realistic to maintain, so this package limits
itself to the same metadata-export mechanism `yiisoft/validator` itself uses.

### `RespectRuleHandler`

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `translator` | `?Yiisoft\Translator\TranslatorInterface` | `null` | If `null`, falls back to `RespectMessageFormatter` directly (English only). |
| `translationCategory` | `string` | `'yii3-respect-validation'` | Category looked up on `$translator`. |

Walks the `Respect\Validation\Result` tree returned by `evaluate()` and emits one
`Yiisoft\Validator\Result` error per failed leaf (and per failed `adjacent`
message), each with its own `valuePath` built from Respect's own nested `Path`
(e.g. failing array keys under `Each`/`KeySet`).

### `RespectMessageFormatter`

`Yiisoft\Translator\MessageFormatterInterface` implementation substituting
Respect's own `{{param}}` placeholder syntax (not ICU). Register it on a
`Yiisoft\Translator\CategorySource` — see [DI configuration](#di-configuration-yii3).

## Security

- No user input is ever interpolated into anything executed — this package only
  moves data between two validation result structures (Respect's `Result` tree and
  Yii3's `Result`); it performs no I/O, SQL, or shell access itself.
- `RespectMessageFormatter` only does `{{key}}` string substitution
  (`strtr()`) — never `eval`/`preg_replace` with the `/e` modifier or similar.

## Examples

See [examples/](examples/) for a runnable script.

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| [`validate-form.php`](examples/validate-form.php) | Wrapping Respect rules on a DTO, reading `Result` errors | no |

## Development

No PHP/Composer on the host — run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

Or with Make:

```bash
make install
make build
make cs-fix
make test
```

CI runs `composer build` on PHP 8.5 only — `respect/validation` requires
`php: >=8.5`, so the usual 8.3/8.4/8.5 matrix does not apply to this package.

## License

[BSD-3-Clause](LICENSE.md)
