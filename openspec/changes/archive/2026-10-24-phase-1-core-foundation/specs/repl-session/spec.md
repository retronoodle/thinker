# Spec Delta

## Purpose

Provides the interactive shell experience: launching Thinker, reading and evaluating one command at a time, preserving state between commands, and ending the session cleanly.

## ADDED Requirements

### Requirement: Launch the interactive shell
Running `bin/thinker` with no arguments SHALL start the interactive REPL directly. Running `bin/thinker help` and `bin/thinker --version` SHALL display the console help and version without starting the REPL.

#### Scenario: Start the shell
- **WHEN** the user runs `bin/thinker` with no arguments
- **THEN** the application prints a startup banner and an input prompt, and waits for input

#### Scenario: Request help instead of a session
- **WHEN** the user runs `bin/thinker help`
- **THEN** the console help text is printed and no input prompt appears

#### Scenario: Request the version
- **WHEN** the user runs `bin/thinker --version`
- **THEN** the application prints its name and version and exits successfully

### Requirement: Evaluate one command per line
The shell SHALL read a single line of PHP per prompt, evaluate it in a persistent session scope, and print the result before prompting again. A command line without a trailing semicolon SHALL be treated as an expression whose value is returned.

#### Scenario: Evaluate an expression
- **WHEN** the user enters `1 + 1`
- **THEN** the shell prints `2` as the result and prompts for the next command

#### Scenario: Execute a statement
- **WHEN** the user enters `$total = 40 + 2;`
- **THEN** the shell evaluates the assignment, prints the assigned value as the result, and prompts for the next command

### Requirement: Preserve session state between commands
Values assigned or declared by earlier commands SHALL remain available to later commands in the same session, and evaluating a new command SHALL NOT re-execute earlier commands.

#### Scenario: Reuse a variable
- **WHEN** the user enters `$name = "World"` and then enters `"Hello, $name!"`
- **THEN** the second command evaluates to `Hello, World!`

#### Scenario: Multiple expressions in sequence
- **WHEN** the user enters `1 + 1` and then enters `2 + 2`
- **THEN** the shell reports `2` for the first command and `4` for the second command

### Requirement: Ignore blank input
A blank or whitespace-only line SHALL NOT be evaluated and SHALL NOT produce a result value or error.

#### Scenario: Press Enter without input
- **WHEN** the user submits an empty line
- **THEN** the shell prints a new prompt without printing a result

### Requirement: End the session cleanly
The shell SHALL exit successfully when the user enters `exit`, `quit`, or `q` (case-insensitive), or when standard input reaches end-of-file. It SHALL print a farewell message for typed exit commands.

#### Scenario: Typed exit command
- **WHEN** the user enters `quit`
- **THEN** the shell prints a farewell message and returns exit code 0

#### Scenario: End of input
- **WHEN** standard input is closed while the shell is prompting
- **THEN** the shell terminates without an error trace and returns exit code 0

### Requirement: Recover after evaluation failures
A parse error, runtime error, or exception raised by a command SHALL be reported without terminating the session, and the shell SHALL prompt for the next command with session state intact.

#### Scenario: Recover from a parse error
- **WHEN** the user enters `foo(` and then enters `1 + 1`
- **THEN** the shell reports a parse error for the first command and still reports `2` for the second command

#### Scenario: Recover from an undefined variable error
- **WHEN** the user enters `$missing + 1` and then enters a valid command
- **THEN** the shell reports a runtime error and continues accepting commands
