# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.4 — 2026-07-31

- Add missing `@api` annotation to the `HealthStatus` enum.
- Docs: sync the check-name pattern with the 1.0.3 fix — `README.md`,
  `README.ru.md` and `llms.txt` now show the `\z`-anchored regex
  (`/^[a-z][a-z0-9_.-]*\z/`) instead of the outdated `$`-anchored one.
- Docs: document that `runByName()` with an unknown name returns `[]`
  (aggregating to `pass`) and that `add()`/constructor silently replace a
  check with the same name.
- Docs: expand the Security section — `/ready`/`/health` expose raw exception
  messages and infrastructure topology (restrict access); no per-check
  timeout exists, set connect/read timeouts inside each check.

## 1.0.3 — 2026-07-25

- Reject trailing newlines in check-name validation: anchor the name pattern
  with `\z` instead of `$` in `HealthResult::NAME_PATTERN` and in
  `CallbackHealthCheck::validateName()` (PCRE `$` matches before a trailing
  `\n`, which let `"<name>\n"` pass and reach the JSON response).

## 1.0.2 — 2026-06-30

- Add `/benchmarks` and `/Makefile` to `.gitattributes` export-ignore.

## 1.0.1 — 2026-06-27

- Migrate test suite from PHPUnit to Testo. Internal change, no public API impact.

## 1.0.0 — 2026-06-02
