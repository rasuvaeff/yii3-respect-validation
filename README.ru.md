# rasuvaeff/yii3-respect-validation
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-respect-validation?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-respect-validation)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-respect-validation/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-respect-validation/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-respect-validation/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-respect-validation/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-respect-validation/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-respect-validation/php)](https://packagist.org/packages/rasuvaeff/yii3-respect-validation)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-respect-validation)](LICENSE.md)
Bridge exposing [Respect/Validation](https://github.com/Respect/Validation) rules as
собственные правила `yiisoft/validator` — можно использовать как атрибут `#[RespectRule]` в свойстве
 `FormModel`/DTO, наряду со встроенными правилами Yii3, при этом результаты проходят через
 тот же конвейер `Result`/`Yiisoft\Translator`.

 > **Используете помощника по кодированию с использованием искусственного интеллекта?** [llms.txt](llms.txt) содержит компактную ссылку
 > API, которой вы можете поделиться с моделью. Авторы: см. [AGENTS.md](AGENTS.md). @@ЛИНИЯ@@
## Требования
| Требование | Версия |
 |-------------|---------|
 | PHP | `>=8.5` (требуется `respect/validation` ^3.1) |
 | `уважение/подтверждение` | `^3.1` |
 | `yiisoft/валидатор` | `^2,6` |
 | `yiisoft/переводчик` | `^3.0` | @@ЛИНИЯ@@
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
Свободные цепочки `v::` также работают, поскольку `RespectRule` принимает либо экземпляр
 `Respect\Validation\Validator`, либо `Respect\Validation\ValidatorBuilder`
 (что возвращает `v::`):

```php
#[RespectRule(new StringType())]
public string $username = '';

// equivalent, built at runtime instead of in the attribute:
$rule = new RespectRule(v::stringType());
```
> Аргументы атрибутов PHP должны быть постоянными выражениями, поэтому только цепочки правил, построенные
 > из вызовов `new Validator(...)`, работают непосредственно в `#[RespectRule(...)]`. `v::`
 > цепочки необходимо строить вне атрибута и передавать конструктору. @@ЛИНИЯ@@
### Свободные цепочки `v::` в модели: `RulesProviderInterface`
Чтобы сохранить свободный API `v::`, не отказываясь от объявлений правил для каждой модели,
 реализуйте `Yiisoft\Validator\RulesProviderInterface` вместо атрибутов —
 `getRules()` — это обычный код времени выполнения, поэтому здесь работает любая цепочка:

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
Оба стиля дают одинаковые результаты и могут быть смешаны: атрибуты для простых
 `новых`-конструируемых цепочек, `getRules()`, где того стоит беглый конструктор. @@ЛИНИЯ@@
### Пропустить/условную проверку
`RespectRule` реализует те же контракты `SkipOnEmptyInterface` / `SkipOnErrorInterface` /
 `WhenInterface`, как и встроенные правила:

```php
#[RespectRule(new StringType(), skipOnEmpty: true, skipOnError: true)]
public string $bio = '';
```
### Сообщения и перевод
Собственные шаблоны сообщений Respect (например, `{{subject}} должно быть строкой`) обрабатываются
 самим обработчиком, а не внутренним `InterpolationRenderer` Respect (которому
 нужны `symfony/translation-contracts`) — поэтому все приложение поддерживает один i18n
 конвейер: `Yiisoft\Translator`.

 По умолчанию (без проводного `TranslatorInterface`) сообщения отображаются на английском языке через
 `RespectMessageFormatter`.

 **В комплекте поставляется полный русский каталог** (`messages/ru/`, всего ~310
 Соблюдайте шаблонные строки). Чтобы активировать его, установите программу чтения сообщений PHP и установите
 локаль приложения на `ru` — поставляемый `config/di.php` подберет ее
 автоматически:

```bash
composer require yiisoft/translator-message-php
```
Тест во время сборки (`russianMessageCatalogTest`) привязывает каждый ключ каталога к установленным
 шаблонам `respect/validation`, поэтому изменение формулировки в восходящем направлении окрашивает CI
 в красный цвет вместо того, чтобы молча удалять переводы.

 Для других языков добавьте собственный каталог рядом с приложением (той же категории):

```php
// messages/de/yii3-respect-validation.php
return [
    '{{subject}} must be a string' => '{{subject}} muss eine Zeichenkette sein',
];
```
### Конфигурация DI (Yii3)
Этот пакет поставляется `config/di.php` + `config/params.php` через `config-plugin` —
, установив его вместе с `yiisoft/config`, достаточно, чтобы получить `RespectRuleHandler`
 с выделенной категорией перевода (`yii3-respect-validation`) с помощью
 `{{placeholder}}`, поддерживающего `{{placeholder}}` RespectMessageFormatter` (шаблоны Respect используют double фигурные скобки
, а не синтаксис ICU, который использует остальная часть `yiisoft/validator` — смешивание двух
 в одном форматере приведет к нарушению замены заполнителя для одного из них).

 Категория читает связанные каталоги `messages/{locale}`, когда
 установлен `yiisoft/translator-message-php`; без него сообщения проходят через
 без перевода (текст шаблона на английском языке).

 При необходимости переопределите имя категории в конфигурации вашего приложения:

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
 | `валидатор` | `Respect\Validation\Validator\|Respect\Validation\ValidatorBuilder` | — | Обернутое правило/цепочка уважения. |
 | `skipOnEmpty` | `bool\|callable\|null` | `ноль` | Пропустить пустое, см. SkipOnEmptyInterface. |
 | `skipOnError` | `бул` | `ложь` | Пропустить предыдущую ошибку, см. SkipOnErrorInterface. |
 | `когда` | `?Закрытие` | `ноль` | Условное выполнение, см. «WhenInterface». |

 `getOptions()` (для экспорта метаданных на стороне клиента/интерфейса, согласно
 `DumpedRuleInterface`) возвращает собственное свойство `$parameters` завернутого валидатора
, если оно присутствует (например, `Between` предоставляет `minValue`/`maxValue` — те же значения, которые
 Respect использует для заполнения своих собственных шаблонов), плюс `skipOnEmpty`/`skipOnError`.
 **Среда выполнения JavaScript не поставляется** — Respect имеет около 150 правил, многие из которых кодируют произвольную логику PHP
 (контрольные суммы Луна, проверки с учетом локали, замыкания `Callback`);
 портировать их все на JS нереально, поэтому этот пакет ограничивает сам
 тем же механизмом экспорта метаданных, который использует сам `yiisoft/validator`. @@ЛИНИЯ@@
### `RespectRuleHandler`
| Параметр | Тип | По умолчанию | Описание |
 |-----------|------|---------|-------------|
 | `переводчик` | `?Yiisoft\Translator\TranslatorInterface` | `ноль` | Если `null`, происходит прямой возврат к `RespectMessageFormatter` (только на английском языке). |
 | `Категория перевода` | `строка` | `'yii3-уважение-проверка'` | Категория была найдена на `$translator`. |

 Обходит дерево `Respect\Validation\Result`, возвращаемое `evaluate()`, и выдает одну ошибку
 `Yiisoft\Validator\Result` для каждого неудачного листа (и для каждого неудачного `смежного` сообщения
), каждое со своим собственным `valuePath`, построенным на основе собственного вложенного `Path`
 Respect (например, неудачные ключи массива в `Each`/`KeySet`). @@ЛИНИЯ@@
### `RespectMessageFormatter`
Реализация `Yiisoft\Translator\MessageFormatterInterface`, заменяющая
 собственный синтаксис заполнителя `{{param}}` Respect (не ICU). Зарегистрируйте его в
 `Yiisoft\Translator\CategorySource` — см. [конфигурация DI](#di-configuration-yii3). @@ЛИНИЯ@@
## Безопасность
- Никакой пользовательский ввод никогда не интерполируется во что-либо выполняемое — этот пакет только
 перемещает данные между двумя структурами результатов проверки (деревом `Result` Respect и `Result`
 Yii3); он сам не выполняет ввод-вывод, SQL или доступ к оболочке.
 - `RespectMessageFormatter` выполняет только замену строки `{{key}}`
 (`strtr()`) — никогда `eval`/`preg_replace` с модификатором `/e` или подобным. @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) для работоспособного сценария.

 | Скрипт | Шоу | Нужен сервер? |
 |--------|-------|:-------------:|
 | [`validate-form.php`](examples/validate-form.php) | Обертывание правил Respect в DTO, чтение ошибок «Result» | нет | @@ЛИНИЯ@@
## Разработка
На хосте нет PHP/Composer — запустите в Docker через образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```
Или с помощью Make:

```bash
make install
make build
make cs-fix
make test
```
CI запускает `composer build` только на PHP 8.5 — для `respect/validation` требуется
 `php: >=8.5`, поэтому обычная матрица 8.3/8.4/8.5 к этому пакету не применима. @@ЛИНИЯ@@
## Лицензия
[BSD-3-пункт](LICENSE.md)
