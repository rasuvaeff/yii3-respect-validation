# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — 2026-07-03

- Initial release: `RespectRule` attribute, `RespectRuleHandler`,
  `RespectMessageFormatter` — Respect/Validation v3 rules as native
  `yiisoft/validator` rules.
- Bundled Russian translation catalog (`messages/ru/`, all Respect template
  strings) with a build-time canary pinning catalog keys to the installed
  `respect/validation` templates.
- `config/di.php` wires the translation category through the bundled catalogs
  when `yiisoft/translator-message-php` is installed, falling back to
  untranslated English otherwise.
