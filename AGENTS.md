# AGENTS.md — yii3-respect-validation

Guidance for AI agents working on this package. Read before changing code.

## What this is

A bridge exposing [Respect/Validation](https://github.com/Respect/Validation) v3
rules as native `yiisoft/validator` rules for Yii3 applications: `#[RespectRule]`
works as a PHP attribute on a `FormModel`/DTO property alongside built-in Yii3
rules, and produces the same `Yiisoft\Validator\Result` shape as any other rule.

Public API (namespace `Rasuvaeff\Yii3RespectValidation\`):

- `RespectRule` — wraps a `Respect\Validation\Validator`/`ValidatorBuilder`;
  implements `DumpedRuleInterface`, `SkipOnEmptyInterface`, `SkipOnErrorInterface`,
  `WhenInterface`.
- `RespectRuleHandler` — walks the Respect `Result` tree, emits one Yii3 `Result`
  error per failed leaf/adjacent message.
- `RespectMessageFormatter` — `Yiisoft\Translator\MessageFormatterInterface` for
  Respect's `{{param}}` (double-brace) placeholder syntax.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Never let Respect messages hit the global (ICU) formatter unprocessed.**
   Respect templates use `{{param}}`, not ICU `{param}` — `RespectRuleHandler`
   must fully translate+format the message itself (own category, own
   `RespectMessageFormatter`) and emit it via `addErrorWithoutPostProcessing()`.
   Using plain `addError()`/`addErrorWithFormatOnly()` here would hand an
   untranslated `{{param}}` string to `yiisoft/validator`'s default ICU
   formatter and corrupt/drop the placeholders.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- **PHP >=8.5 only.** `respect/validation` (all of 3.0.0–3.1.2) requires
  `php: >=8.5` — there is no way to support the usual 8.3/8.4/8.5 matrix while
  depending on Respect v3. `.github/workflows/build.yml`'s matrix is `['8.5']`
  only (not the usual three), and the `prefer-lowest`/`coverage`/`compatibility`
  jobs all pin `'8.5'` too. Do not "fix" this back to the standard matrix without
  re-checking whether Respect has widened its PHP constraint.
- **`v::` facade returns `ValidatorBuilder`, not `Validator`.** `ValidatorBuilder`
  does **not** implement `Respect\Validation\Validator` (only `Nameable`,
  `ShortCircuitable`) even though it has a structurally identical `evaluate()`.
  `RespectRule`'s constructor accepts a union
  `Respect\Validation\Validator|Respect\Validation\ValidatorBuilder` for exactly
  this reason — do not narrow it back to just `Validator`, that would reject
  every `v::`-built chain.
- **`Result::$template` is a lookup key, not literal text.** Respect stores an
  internal id (e.g. `Validator::TEMPLATE_STANDARD = '__standard__'`, or a
  validator-specific constant like `Length::TEMPLATE_WRONG_TYPE`) on
  `Respect\Validation\Result::$template`. The actual `{{param}}`-templated
  English text lives in `#[Template(default, inverted, id)]` attributes on the
  validator class, resolved via `Respect\Validation\Message\TemplateRegistry`.
  `RespectRuleHandler::resolveTemplate()` does this lookup and picks
  `default`/`inverted` based on `$node->hasInvertedMode`; on lookup failure
  (custom validator with no `#[Template]`) it falls back to the raw
  `$node->template` string rather than throwing.
- **`RespectRule::getOptions()` reads the wrapped validator's `$parameters`
  property**, not its constructor arguments — many Respect validators (anything
  extending `Validators\Core\Envelope`, e.g. `Between`) don't store constructor
  args as properties on themselves; `$parameters` is the same array Respect uses
  internally to fill in its own templates, so it happens to be the most reliable
  generic source of "configuration" across arbitrary rules. Returns `[]` for
  rules without that property (most `Simple`-based leaf rules, `ValidatorBuilder`
  itself) — this is expected, not a bug.
- **No JS client-validation runtime is shipped**, by deliberate scope decision —
  Respect has ~150 rules, many encoding arbitrary PHP logic (Luhn checksums,
  locale-aware checks, `Callback` closures) that can't be ported to JS
  affordably. `getOptions()` only exports metadata (same mechanism
  `yiisoft/validator` core uses for its own rules) — do not add a JS runtime here
  without an explicit, separate scope discussion.
- `valuePath` for each error is built by walking Respect's own `Path` linked
  list (`$node->path->value` / `->parent`), **not** anything from
  `Yiisoft\Validator\ValidationContext` — Yii3's own property-path prefixing
  happens one layer up when this rule's `Result` is merged into the full
  validation result.
- Code: `declare(strict_types=1)`, `#[\Override]`, explicit types.
  `RespectRule` is `final class` (not readonly) — it needs mutable
  `$skipOnEmpty`/`$skipOnError`/`$when` for the clone-based `with*` traits shared
  with native yii3 rules (`SkipOnEmptyTrait`/`SkipOnErrorTrait`/`WhenTrait`).
  `RespectRuleHandler` and `RespectMessageFormatter` are `final readonly class`.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
