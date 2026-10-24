# Tasks

## 1. Test Harness Setup

- [x] 1.1 Add `autoload-dev` mapping `Thinker\Tests\` to `tests/` in `composer.json` and run `composer dump-autoload` to confirm it loads
- [x] 1.2 Add a committed `phpunit.xml.dist` targeting PHPUnit 10 with a `tests/` suite, and confirm the existing `phpunit.xml` ignore rule does not hide it
- [x] 1.3 Add `tests/` scaffolding with a base test case, then run `vendor/bin/phpunit` and confirm the empty suite exits successfully

## 2. REPL Session Loop

- [x] 2.1 Refactor `ReplEngine` to accept an injectable input stream (defaulting to `STDIN`) and an `OutputInterface`, and verify with a memory-stream test that a line is read and echoed as evaluated
- [x] 2.2 Replace accumulated `$contextCode` replay with a bound closure that extracts stored variables and recaptures locals after eval, and verify `$name = "World"` followed by `"Hello, $name!"` reports `Hello, World!`
- [x] 2.3 Implement expression-first classification with statement fallback, and verify `1 + 1` then `2 + 2` report `2` then `4`, while `unset($x)` executes as a statement
- [x] 2.4 Ignore blank and whitespace-only input, and verify no result or error line is printed for an empty line
- [x] 2.5 Handle `exit`, `quit`, `q` (case-insensitive), and stream EOF as clean termination, and verify each path exits with code 0 and EOF produces no PHP warning
- [x] 2.6 Catch `ParseError`, `Error`, and `Exception` per command while preserving scope changes, and verify a parse error and an undefined-variable error each leave the session usable
- [x] 2.7 Keep captured `echo`/`print` output ahead of the formatted result, and verify `echo "hi"; return 5;` prints `hi` then `5`
- [x] 2.8 Add `ReplEngineTest` cases covering all scenarios in `specs/repl-session/spec.md` and confirm they pass with `vendor/bin/phpunit`

## 3. Result Formatting

- [x] 3.1 Replace the scalar branches in `OutputFormatter` with a single renderer covering null, booleans, numbers, and escaped strings, and verify quote/backslash escaping with unit tests
- [x] 3.2 Rewrite array rendering as a recursive depth-aware renderer, and verify indexed, associative, empty, and nested arrays match `specs/result-formatting/spec.md`
- [x] 3.3 Render objects through `toArray()` when present and class name otherwise, and verify both cases with anonymous-class fixtures
- [x] 3.4 Categorize error output as `Parse Error`, `Fatal Error`, or `Exception`, and verify each category appears through `BufferedOutput` tests
- [x] 3.5 Add `OutputFormatterTest` covering every scenario in `specs/result-formatting/spec.md` and confirm it passes

## 4. Laravel Context Bootstrap

- [x] 4.1 Keep the boot-once guard and install the container globally, and verify repeated `boot()` calls return the same container instance
- [x] 4.2 Bind `db` (capsule), `events` (dispatcher), and `files` (filesystem) in the container, and verify each binding resolves from the global container after boot
- [x] 4.3 Attach the event dispatcher to the capsule before `bootEloquent()`, and verify the capsule reports the registered dispatcher in a fixture test
- [x] 4.4 Discover `config/database.php` by walking up from the current working directory and register the default connection, and verify with a temporary fixture project that the default connection name is applied
- [x] 4.5 Catch bootstrap `Throwable` in `ReplEngine::initialize()`, print the reason, and continue as a plain PHP shell, and verify a failing bootstrap fixture still evaluates `1 + 1`
- [x] 4.6 Add `LaravelBootstrapTest` using temporary fixtures and no external database, and confirm it passes without MySQL available

## 5. CLI Integration

- [x] 5.1 Wire `ReplCommand` to pass `STDIN` and the console output into `ReplEngine`, keeping `repl` as the default command, and verify `bin/thinker` starts the prompt
- [x] 5.2 Verify `bin/thinker help` prints help and `bin/thinker --version` prints the name and version without entering the REPL
- [x] 5.3 Add a CLI test that runs the command with piped input (`1 + 1`, then `quit`) and verifies both the result output and the success exit code

## 6. Integration Verification

- [x] 6.1 Run `vendor/bin/phpunit` and confirm the full suite passes with no skipped tests
- [x] 6.2 Run `printf '1 + 1\n$name = "World"\n"Hello, $name!"\nquit\n' | php bin/thinker` and confirm sequential results are correct, then confirm the process exits with code 0
- [x] 6.3 Run `php -l` over `bin/thinker` and all `src/` files, and confirm no syntax errors
- [x] 6.4 Review the Phase 1 checklist in `docs/development-plan.md` against the implemented behavior and confirm no Phase 1 deliverable is missing

## Workflow follow-up

- Archive the change after the project's review requirements are satisfied.
