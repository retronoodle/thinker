# Spec Delta

## Purpose

Provides best-effort Laravel services to evaluated commands so a Thinker session behaves like a familiar Laravel environment, while still working as a plain PHP shell outside a Laravel project.

## ADDED Requirements

### Requirement: Provide a global service container
Booting the Laravel context SHALL install a shared service container as the global container instance so evaluated code can resolve container bindings.

#### Scenario: Resolve a binding after boot
- **WHEN** the session has booted and a command resolves the `events` binding from the global container
- **THEN** the registered event dispatcher is returned

### Requirement: Configure a database capsule
When a Laravel database configuration is discoverable, bootstrapping SHALL register the default connection with Eloquent's capsule, expose the capsule globally, and boot Eloquent so models can query the configured database.

#### Scenario: Default connection is used
- **WHEN** a Laravel project's `config/database.php` defines a default connection
- **THEN** after boot the Eloquent capsule uses that connection

#### Scenario: Eloquent is available
- **WHEN** the session has booted inside a Laravel project with a database configuration
- **THEN** the user can evaluate an Eloquent model query without further setup

### Requirement: Register core Laravel services
Bootstrapping SHALL register an event dispatcher and a filesystem service in the container so evaluated commands can use them.

#### Scenario: Event dispatcher is registered
- **WHEN** the session has booted and a command resolves the `events` binding from the container
- **THEN** an event dispatcher instance is returned

#### Scenario: Filesystem is registered
- **WHEN** the session has booted and a command resolves the `files` binding from the container
- **THEN** a filesystem instance is returned

### Requirement: Degrade gracefully without a Laravel project
When no Laravel application can be detected or bootstrapping fails, the shell SHALL report that Laravel context is unavailable, SHALL continue as a plain PHP shell, and SHALL NOT terminate the session.

#### Scenario: Run outside a Laravel project
- **WHEN** the user starts Thinker in a directory with no Laravel configuration
- **THEN** the shell reports that Laravel context could not be loaded and still accepts and evaluates PHP commands

#### Scenario: Boot failure
- **WHEN** bootstrapping the Laravel context throws an error
- **THEN** the shell reports the failure reason and continues with the basic PHP REPL

### Requirement: Boot once per session
The Laravel context SHALL be initialized once at session start and SHALL NOT be re-bootstrapped for each evaluated command.

#### Scenario: Repeated evaluation
- **WHEN** the user evaluates several commands in one session
- **THEN** the container is booted once and its bindings remain shared across commands
