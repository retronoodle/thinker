# Design

## Context

Phase 1 already has scaffolding at `bin/thinker`, `src/Application.php`, `src/Commands/ReplCommand.php`, `src/Repl/ReplEngine.php`, `src/Output/OutputFormatter.php`, and `src/Laravel/LaravelBootstrap.php`, but it has no tests and two structural defects:

- `ReplEngine` accumulates every evaluated line into `$contextCode` and re-evals the whole buffer (`src/Repl/ReplEngine.php:78-88`). Because expression commands are stored with a leading `return`, the first `return` short-circuits eval on every later command, so a second expression never runs and earlier expressions are re-executed.
- Input is read with `fgets` on `php://stdin` opened per iteration (`src/Repl/ReplEngine.php:64-66`). EOF yields `false`, which becomes an empty command instead of ending the session, and the loop is not testable without real stdin.

See `proposal.md` for motivation and `specs/` for the behavior contract. Constraints: PHP 8.1+, existing Composer dependencies only, no Laravel application required to run Thinker.

## Goals / Non-Goals

**Goals:**
- A correct, testable read-eval-print loop with persistent session variables.
- Deterministic result/error rendering through the existing console output abstraction.
- Boot-once Laravel services with an explicit graceful-fallback path.
- Automated PHPUnit coverage runnable in CI without a database server.

**Non-Goals:**
- Loading `bootstrap/app.php`, `.env`, facades, or full framework kernels (Phase 2).
- Multi-line input, history, autocomplete, highlighting, or code intelligence (Phases 3-9).
- Sandboxing or resource limits for evaluated code; Thinker is a local developer tool with full process privileges.
- Public API stability guarantees beyond keeping existing class names and the `bin/thinker` interface.

## Decisions

### Session scope: capture locals instead of replaying code
`ReplEngine` will hold an `array $variables`. Each command runs inside a closure bound to the engine that `extract()`s the stored variables (with `EXTR_SKIP` so engine internals win), evals the code, and in a `finally` block recaptures `get_defined_vars()` minus a fixed denylist of engine-local names (`code`, `result`, `exception`, `output`, `depth`, etc.).

- Rationale: preserves assignments without re-running earlier commands, avoids the `return` short-circuit, and keeps memory proportional to live variables rather than to session length.
- Alternatives rejected: replaying accumulated code (current bug); generating a temporary PHP file and `include`-ing it in a function scope (same capture need, more I/O); a full variable-bag object with magic accessors (cannot model plain PHP variables or assignments).
- Consequence: values persist by PHP copy-on-write semantics. References spanning two variables are not preserved across commands; this matches the Phase 1 contract and is acceptable until a later phase revisits it.

### Input: injectable line source
The engine takes a readable stream resource (default: `STDIN`) and an `OutputInterface`. It reads with `fgets`, treats `false` as end-of-input, trims the trailing newline, and stops the loop. `ReplCommand` supplies defaults; tests supply an `fopen('php://memory', 'r+')` stream.

- Rationale: `fgets` is the simplest correct primitive for a PHP expression REPL and makes EOF handling explicit; injecting the stream keeps the loop testable. Symfony's question helpers are avoided because they interpret backslashes and interact poorly with raw PHP.
- Alternative rejected: `CommandTester::setInputs()` (requires `StreamableInputInterface` and question helpers, and still cannot model EOF cleanly).

### Command classification: expression-first with statement fallback
If a trimmed line does not end in `;` and does not start with a known statement keyword (`if`, `for`, `foreach`, `while`, `function`, `class`, `interface`, `trait`, `switch`, `try`, `return`, `echo`, `print`, `unset`, `global`, `static`, `declare`, `namespace`, `use`), evaluate it as `return <line>;`. On `ParseError`, retry once as a plain statement. Lines ending in `;` are always statements.

- Rationale: parse errors occur before execution, so the fallback cannot duplicate side effects; this handles both `1 + 1` and `unset($x)`. It supersedes the ad-hoc keyword check that only looked at the first word.
- Alternative rejected: requiring semicolons for all input (worse tinker-like UX).

### Output: pure recursive renderer
`OutputFormatter` renders values into strings with a depth parameter and a single terminal write per result:
- `null` → `null`; bool → `true`/`false`; int/float → numeric representation; string → double-quoted with `"` and `\` escaped; array → keyed, indented, recursive (empty → `[]`); object → `className { ...toArray()... }` or `className` when no `toArray()` exists.
- Output captured with `ob_start()` during eval is written before the formatted result; the buffer is closed in `finally`.
- Errors are prefixed by category: `Parse Error`, `Fatal Error`, `Exception`.
- Rationale: fixes indentation bugs in the current nested-array branch and avoids `var_export`, which leaks PHP syntax into user-facing output.
- Alternative rejected: Symfony VarDumper (new dependency, overkill for Phase 1).

### Bootstrap: best-effort Capsule, boot-once, explicit failure
`LaravelBootstrap::boot()` keeps its guard and, on success, installs the container globally and binds `db` (capsule), `events`, and `files`. The event dispatcher is attached to the capsule via `setEventDispatcher()` before `bootEloquent()` so model events work. Connection discovery requires a `config/database.php` found by walking up from the current working directory; when none is found, boot fails with a reportable error so `ReplEngine::initialize()` can announce that Laravel context is unavailable and continue as a plain PHP shell. Any `Throwable` during boot propagates to `ReplEngine::initialize()`, which prints the reason and continues.

- Rationale: gives meaningful Laravel behavior without claiming full framework boot; deferring `bootstrap/app.php` keeps Phase 1 independent of a Laravel install.
- Alternative rejected: depending on `laravel/framework` or booting a real application kernel (Phase 2 scope, heavy dependency).

### Testing: committed PHPUnit config and stream-based tests
Add `phpunit.xml.dist` (the current `.gitignore` excludes `phpunit.xml`), an autoload-dev PSR-4 mapping `Thinker\Tests\` → `tests/`, and suites under `tests/`:
- `ReplEngineTest`: drives the engine with a memory stream and buffered output; covers expressions, assignments across commands, multiple sequential expressions, blank lines, exit commands, EOF, parse errors, runtime errors, recovered state, and echoed output.
- `OutputFormatterTest`: covers every value type and nesting case, using a `BufferedOutput`.
- `LaravelBootstrapTest`: uses temporary fixture directories containing a minimal `config/database.php`; asserts global container identity, bindings, and boot-once behavior without opening a database connection.
- Rationale: verifies the spec scenarios directly and runs anywhere Composer is installed. SQLite in-memory can back a connection test if the driver is available, but tests must not require MySQL.

## Risks / Trade-offs

- **Scope capture denylist drift** → Engine-local variables could leak into user scope or be overwritten. Mitigation: centralize the denylist in one constant, prefix engine locals with `__`, and add a test asserting only user variables persist.
- **`eval` executes with full process privileges** → A command can damage the developer's machine. Accepted: Thinker is an interactive dev tool, equivalent to Tinker; document it and do not expose the REPL over a network in Phase 1.
- **Expression-first fallback evaluates input twice on parse failure** → Only parse-time, no execution occurs on the failed attempt. Mitigation: keep classification conservative and test `unset`/`echo` fallbacks.
- **Copy-on-write loses inter-variable references** → Some advanced state patterns behave differently than a single script. Documented limitation, revisited if later phases need reference semantics.
- **Bootstrap discovery by walking up `config/database.php` can select an unrelated parent project** → Acceptance for Phase 1 since Thinker is run from the target project; Phase 2 replaces this with explicit application bootstrap.
- **No `.env` loading means `env()`-based database configs may fail** → Boot failure is caught and reported; users can rely on plain PHP until Phase 2.

## Migration Plan

Refactor in place; there are no released versions or persisted state. Keep existing class names and namespaces so later phases' imports remain valid. Rollback is reverting the working tree; no data migration or config changes are required. Land tests alongside the refactor so the behavioral fixes are verifiable before Phase 2 begins.

## Open Questions

None blocking.
