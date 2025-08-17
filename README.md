# Thinker - Laravel Tinker Replacement

A modern, enhanced replacement for Laravel's Tinker REPL with superior autocomplete and intelligent code assistance.

## Installation

1. Install dependencies:
```bash
composer install
```

2. Make the binary executable (Linux/Mac):
```bash
chmod +x bin/thinker
```

## Usage

Run the REPL:
```bash
php bin/thinker
```

Or if installed globally:
```bash
./bin/thinker
```

## Features (Phase 1)

- ✅ Basic PHP REPL functionality
- ✅ Laravel application bootstrapping (when available)
- ✅ Simple command execution loop
- ✅ Basic output formatting
- ✅ Error handling and recovery

## Commands

- Type any PHP code and press Enter to execute
- Type `exit`, `quit`, or `q` to quit
- Use Ctrl+C to force quit

## Examples

```php
>>> 1 + 1
=> 2

>>> $name = "World"
=> "World"

>>> "Hello, $name!"
=> "Hello, World!"

>>> [1, 2, 3]
=> [
  0 => 1
  1 => 2
  2 => 3
]
```

## Development Status

This is Phase 1 of development. See `docs/development-plan.md` for the full roadmap.