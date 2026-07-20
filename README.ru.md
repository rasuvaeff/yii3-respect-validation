# rasuvaeff/yii3-respect-validation

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-respect-validation?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-respect-validation)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-respect-validation/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-respect-validation/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-respect-validation/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-respect-validation/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-respect-validation/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-respect-validation/php)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-respect-validation)](LICENSE.md)
[English version](README.md)

Мост, представляющий правила [Respect/Validation](https://github.com/Respect/Validation)
как нативные правила `yiisoft/validator` — можно использовать как атрибут
`#[RespectRule]` на свойстве `FormModel`/DTO рядом со встроенными правилами Yii3,
при этом результаты проходят через тот же конвейер `Result`/`Yiisoft\Translator`.

> **Используете AI-ассистента?** В [llms.txt](llms.txt) — компактный
> API-справочник, которым можно поделиться с моделью. Контрибьюторам: см.
> [AGENTS.md](AGENTS.md).

## Требования

| Требование | Версия |
|-------------|---------|
| PHP | `>=8.5` (требуется `respect/validation` ^3.1) |
| `respect/validation` | `^3.1` |
| `yiisoft/validator` | `^2.6` |
| `yiisoft/translator` | `^3.0` |

## Установка

```bash
composer require rasuvaeff/yii3-respect-validation
```

## Использование

```php
use Rasuvaeff\Yii3RespectValidation\RespectRule;
use Respect\Validation\Validators\AllOf;
use Respect\Validation\Validators\Between;
use Respect\Validation\Validators\Length;
use Respect\Validation\Validators\StringType;

final class RegisterForm
{
    #[RespectRule(new AllOf(new StringType(), new Length(new Between(1, 50))))]
    public string $username = '';
}
```

```php
use Yiisoft\Validator\Validator;

$result = (new Validator())->validate(new RegisterForm());

$result->isValid();                          // false
$result->getPropertyErrorMessages('username'); // ['Username must be between 1 and 50']
```

Fluent-цепочки `v::` тоже работают, поскольку `RespectRule` принимает как
экземпляр `Respect\Validation\Validator`, так и `Respect\Validation\ValidatorBuilder`
(то, что возвращает `v::`):

```php
#[RespectRule(new StringType())]
public string $username = '';

// equivalent, built at runtime instead of in the attribute:
$rule = new RespectRule(v::stringType());
```

> Аргументы PHP-атрибутов должны быть константными выражениями, поэтому внутри
> `#[RespectRule(...)]` работают только цепочки, собранные из вызовов
> `new Validator(...)`. Цепочки `v::` нужно строить вне атрибута и передавать в
> конструктор.

### Fluent-цепочки `v::` на модели: `RulesProviderInterface`

Чтобы сохранить fluent-API `v::`, не отказываясь от объявления правил на уровне
модели, реализуйте `Yiisoft\Validator\RulesProviderInterface` вместо атрибутов —
`getRules()` это обычный runtime-код, где работает любая цепочка:

```php
use Respect\Validation\Validator as v;
use Yiisoft\Validator\RulesProviderInterface;

final class RegisterForm implements RulesProviderInterface
{
    public string $username = '';
    public string $email = '';

    public function getRules(): iterable
    {
        return [
            'username' => new RespectRule(v::stringType()->length(v::between(1, 50))),
            'email' => new RespectRule(v::email()),
        ];
    }
}
```

Оба стиля дают идентичные результаты и могут смешиваться: атрибуты — для
простых цепочек, конструируемых через `new`, `getRules()` — там, где fluent-API
оправдан.

### Пропуск / условная валидация

`RespectRule` реализует те же контракты `SkipOnEmptyInterface` /
`SkipOnErrorInterface` / `WhenInterface`, что и встроенные правила:

```php
#[RespectRule(new StringType(), skipOnEmpty: true, skipOnError: true)]
public string $bio = '';
```

### Сообщения и перевод

Собственные шаблоны сообщений Respect (например, `{{subject}} must be a string`)
рендерятся самим хендлером, а не внутренним `InterpolationRenderer` из Respect
(ему нужен `symfony/translation-contracts`) — поэтому во всём приложении остаётся
единый i18n-конвейер: `Yiisoft\Translator`.

По умолчанию (без подключённого `TranslatorInterface`) сообщения рендерятся на
английском через `RespectMessageFormatter`.

**С пакетом поставляется полный русский каталог** (`messages/ru/`, все ~310
строк шаблонов Respect). Чтобы активировать его, установите PHP-message-reader и
задайте приложению локаль `ru` — поставляемый `config/di.php` подхватит её
автоматически:

```bash
composer require yiisoft/translator-message-php
```

Сборочный тест (`RussianMessageCatalogTest`) привязывает каждый ключ каталога к
шаблонам установленного `respect/validation`, поэтому upstream-изменение формулировок
краснит CI вместо того, чтобы молча потерять перевод.

Для других локалей добавьте свой каталог рядом с приложением (та же категория):

```php
// messages/de/yii3-respect-validation.php
return [
    '{{subject}} must be a string' => '{{subject}} muss eine Zeichenkette sein',
];
```

### DI-конфигурация (Yii3)

Пакет поставляет `config/di.php` + `config/params.php` через `config-plugin` —
его установки рядом с `yiisoft/config` достаточно, чтобы `RespectRuleHandler`
получил выделенную категорию перевода (`yii3-respect-validation`) с
учитывающим `{{placeholder}}` `RespectMessageFormatter` (шаблоны Respect
используют двойные фигурные скобки, а не ICU-синтаксис, как остальной
`yiisoft/validator` — смешивание их под одним форматером сломало бы подстановку
плейсхолдеров у одного из них).

Категория читает bundled-каталоги `messages/{locale}`, когда установлен
`yiisoft/translator-message-php`; без него сообщения проходят без перевода
(английский текст шаблона).

При необходимости переопределите имя категории в конфиге приложения:

```php
// config/params.php
return [
    'rasuvaeff/yii3-respect-validation' => [
        'translation.category' => 'yii3-respect-validation',
    ],
];
```

## Компоненты

### `RespectRule`

| Параметр | Тип | По умолчанию | Описание |
|-----------|------|---------|-------------|
| `validator` | `Respect\Validation\Validator\|Respect\Validation\ValidatorBuilder` | — | Обёрнутое правило/цепочка Respect. |
| `skipOnEmpty` | `bool\|callable\|null` | `null` | Пропуск при пустом значении, см. `SkipOnEmptyInterface`. |
| `skipOnError` | `bool` | `false` | Пропуск при наличии предыдущей ошибки, см. `SkipOnErrorInterface`. |
| `when` | `?Closure` | `null` | Условное выполнение, см. `WhenInterface`. |

`getOptions()` (для экспорта метаданных frontend/клиентской стороны, согласно
`DumpedRuleInterface`) возвращает собственное свойство `$parameters` обёрнутого
валидатора, если оно есть (например, `Between` открывает `minValue`/`maxValue` —
те же значения, что Respect использует для своих шаблонов), плюс
`skipOnEmpty`/`skipOnError`. **JS-runtime не поставляется** — у Respect около
150 правил, многие из них кодируют произвольную PHP-логику (контрольные суммы
Льюна, локаль-зависимые проверки, `Callback`-замыкания); портировать их все на
JS нереалистично в поддерживаемом виде, поэтому пакет ограничивается тем же
механизмом экспорта метаданных, что использует сам `yiisoft/validator`.

### `RespectRuleHandler`

| Параметр | Тип | По умолчанию | Описание |
|-----------|------|---------|-------------|
| `translator` | `?Yiisoft\Translator\TranslatorInterface` | `null` | Если `null` — прямой фоллбэк на `RespectMessageFormatter` (только английский). |
| `translationCategory` | `string` | `'yii3-respect-validation'` | Категория, запрашиваемая у `$translator`. |

Обходит дерево `Respect\Validation\Result`, возвращаемое `evaluate()`, и эмитит
одну ошибку `Yiisoft\Validator\Result` на каждый провалившийся лист (и на каждое
провалившееся `adjacent`-сообщение), каждое со своим `valuePath`, собранным из
собственного вложенного `Path` Respect (например, провалившиеся ключи массива
под `Each`/`KeySet`).

### `RespectMessageFormatter`

Реализация `Yiisoft\Translator\MessageFormatterInterface`, подставляющая
собственный плейсхолдер-синтаксис Respect `{{param}}` (не ICU). Зарегистрируйте
его на `Yiisoft\Translator\CategorySource` — см. [DI-конфигурация](#di-конфигурация-yii3).

## Безопасность

- Никакой пользовательский ввод никогда не интерполируется во что-либо
  исполняемое — пакет только перекладывает данные между двумя структурами
  результата валидации (дерево `Result` Respect и `Result` Yii3); он не выполняет
  I/O, SQL или shell-доступ.
- `RespectMessageFormatter` делает только строковую подстановку `{{key}}`
  (`strtr()`) — никогда `eval`/`preg_replace` с модификатором `/e` и подобное.

## Примеры

См. [examples/](examples/) — запускаемый скрипт.

| Скрипт | Показывает | Нужен сервер? |
|--------|-------|:-------------:|
| [`validate-form.php`](examples/validate-form.php) | Обёртывание правил Respect на DTO, чтение ошибок `Result` | нет |

## Разработка

На хосте нет PHP/Composer — запускайте через Docker-образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

Или через Make:

```bash
make install
make build
make cs-fix
make test
```

CI запускает `composer build` только на PHP 8.5 — `respect/validation` требует
`php: >=8.5`, поэтому стандартная матрица 8.3/8.4/8.5 к этому пакету не
применима.

## Лицензия

[BSD-3-Clause](LICENSE.md)
