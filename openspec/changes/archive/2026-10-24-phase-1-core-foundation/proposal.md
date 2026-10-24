# Proposal

## Why

Thinker needs a dependable interactive PHP shell before any of the higher-value features (Laravel context, history, autocomplete, highlighting) can be built on top of it. Phase 1 currently exists only as untested scaffolding: the REPL cannot reliably evaluate more than one expression, has no automated tests, and its Laravel bootstrap is not verified against a real application. This change defines and hardens the core foundation so later phases extend a stable base instead of a prototype.

## What Changes

- Define the CLI entrypoint contract: `bin/thinker` launches the interactive REPL as the default Symfony Console command, with `--help` and `--version` available.
- Define the read-eval-print loop: prompt, read one line, evaluate, print result, repeat; blank input is ignored; `exit`, `quit`, `q`, EOF, and Ctrl+C end the session cleanly.
- Fix session state so values assigned in one command remain available to later commands without re-running earlier expressions.
- Define output formatting for null, booleans, numbers, strings, arrays, and objects, plus error rendering for parse, runtime, and exception failures.
- Define basic Laravel-oriented bootstrapping (container, database capsule, event dispatcher, filesystem) that degrades gracefully to a plain PHP shell when no Laravel application is present.
- Add automated coverage for the REPL engine, result formatter, and bootstrap using an in-memory/temporary project fixture.
- Out of scope for this change: multi-line input, command history, autocomplete, syntax highlighting, advanced error formatting, code intelligence, configuration/preferences, and full application bootstrap via Laravel's `bootstrap/app.php`. These belong to later phases in `docs/development-plan.md`.

## Capabilities

### New Capabilities

- `repl-session`: launching Thinker, the interactive evaluate/print loop, variable persistence between commands, exit/EOF handling, and recovery after evaluation errors.
- `result-formatting`: human-readable rendering of evaluated values and of parse/runtime/exception errors in the terminal.
- `laravel-bootstrap`: best-effort bootstrapping of a Laravel service container, database capsule, event dispatcher, and filesystem, with graceful fallback to plain PHP when no application is detected.

### Modified Capabilities

None. `openspec/specs/` has no existing capabilities.

## Impact

- Code: `bin/thinker`, `src/Application.php`, `src/Commands/ReplCommand.php`, `src/Repl/ReplEngine.php`, `src/Output/OutputFormatter.php`, `src/Laravel/LaravelBootstrap.php`.
- Tests/tooling: new `tests/` suite and PHPUnit configuration; `phpunit.xml` is currently gitignored, so a committed `phpunit.xml.dist` (or a `.gitignore` adjustment) is required.
- Dependencies: existing `symfony/console`, `illuminate/container`, `illuminate/database`, `illuminate/events`, `illuminate/filesystem`, and `phpunit/phpunit`. No new runtime dependencies planned.
- Behavior: `bin/thinker` remains the primary interface, but command chaining within a session becomes correct; no public API is removed. No breaking changes.
