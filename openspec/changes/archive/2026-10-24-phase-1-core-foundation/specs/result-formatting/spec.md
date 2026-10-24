# Spec Delta

## Purpose

Defines how evaluated values and evaluation failures are rendered in the terminal so results are readable, distinguishable by type, and consistently presented.

## ADDED Requirements

### Requirement: Render scalar results
The shell SHALL render the result of each successful evaluation according to its type: `null` as `null`, booleans as `true` or `false`, numbers as their numeric value, and strings quoted with escaped quote and backslash characters.

#### Scenario: Null result
- **WHEN** a command evaluates to `null`
- **THEN** the shell displays `=> null`

#### Scenario: Boolean result
- **WHEN** a command evaluates to `false`
- **THEN** the shell displays `=> false`

#### Scenario: Numeric result
- **WHEN** a command evaluates to `42`
- **THEN** the shell displays `=> 42`

#### Scenario: String result
- **WHEN** a command evaluates to a string containing a double quote
- **THEN** the shell displays the string in double quotes with the quote escaped

### Requirement: Render array results
The shell SHALL render arrays with their keys and values, including nested arrays and nested objects, and SHALL render an empty array as `[]`.

#### Scenario: Indexed array
- **WHEN** a command evaluates to `[1, 2, 3]`
- **THEN** the shell lists each key and value on its own line inside brackets

#### Scenario: Associative array
- **WHEN** a command evaluates to `["name" => "Tim"]`
- **THEN** the shell displays the string key and its string value

#### Scenario: Empty array
- **WHEN** a command evaluates to an empty array
- **THEN** the shell displays `=> []`

#### Scenario: Nested array
- **WHEN** a command evaluates to `["rows" => [1, 2]]`
- **THEN** the shell displays the nested values with indentation that shows they belong to the outer key

### Requirement: Render object results
The shell SHALL render the value returned by an object's `toArray()` method when available, and otherwise SHALL display at least the object's class name.

#### Scenario: Object with toArray
- **WHEN** a command evaluates to an object whose `toArray()` returns `["id" => 1]`
- **THEN** the shell displays the class name and the key/value pairs from `toArray()`

#### Scenario: Object without toArray
- **WHEN** a command evaluates to an object with no `toArray()` method
- **THEN** the shell displays the object's class name

### Requirement: Render captured output before the result
Output written by the evaluated command SHALL be printed before the formatted result, and formatting SHALL NOT alter or swallow that output.

#### Scenario: Command that echoes
- **WHEN** the user enters `echo "hi"; return 5;`
- **THEN** the shell prints `hi` followed by the formatted result `5`

### Requirement: Report errors distinguishably
The shell SHALL report evaluation failures with a category label that distinguishes parse errors, runtime errors, and exceptions, and SHALL include the failure message.

#### Scenario: Parse error
- **WHEN** a command contains invalid PHP syntax
- **THEN** the shell prints a message identifying the failure as a parse error and includes the parser message

#### Scenario: Runtime error
- **WHEN** a command triggers a PHP `Error` such as calling a method on `null`
- **THEN** the shell prints a message identifying the failure as a fatal or runtime error and includes the error message

#### Scenario: Exception
- **WHEN** a command throws an exception
- **THEN** the shell prints a message identifying the failure as an exception and includes the exception message
