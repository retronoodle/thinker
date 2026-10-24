<?php

namespace Thinker\Repl;

use Thinker\Laravel\LaravelBootstrap;
use Thinker\Output\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter as ConsoleFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ReplEngine
{
    private const STATEMENT_KEYWORDS = [
        'if', 'for', 'foreach', 'while', 'function', 'class', 'interface',
        'trait', 'switch', 'try', 'return', 'echo', 'print', 'unset',
        'global', 'static', 'declare', 'namespace', 'use',
    ];

    private const EXIT_COMMANDS = ['exit', 'quit', 'q'];

    private const INTERNAL_VARIABLES = ['__code', '__variables', '__locals'];

    /** @var resource */
    protected $stream;

    protected OutputInterface $output;
    protected OutputFormatter $formatter;
    protected LaravelBootstrap $laravel;
    protected bool $running = true;

    /** @var array<string, mixed> */
    protected array $variables = [];

    /**
     * @param resource|null $stream
     */
    public function __construct(
        $stream = null,
        ?OutputInterface $output = null,
        ?LaravelBootstrap $laravel = null
    ) {
        $this->stream = $stream ?? STDIN;
        $this->output = $output ?? new ConsoleOutput();
        $this->formatter = new OutputFormatter($this->output);
        $this->laravel = $laravel ?? new LaravelBootstrap();
    }

    public function run(): void
    {
        $this->initialize();

        while ($this->running) {
            $this->processCommand();
        }
    }

    protected function initialize(): void
    {
        $this->output->writeln('<info>Initializing Laravel context...</info>');

        try {
            $this->laravel->boot();
            $this->output->writeln('<info>Laravel context loaded successfully!</info>');
        } catch (\Throwable $exception) {
            $this->output->writeln(
                '<comment>Could not load Laravel context: '
                . ConsoleFormatter::escape($exception->getMessage())
                . '</comment>'
            );
            $this->output->writeln('<comment>Continuing with basic PHP REPL...</comment>');
        }

        $this->output->writeln('');
    }

    protected function processCommand(): void
    {
        $this->output->write('<question>>>> </question>');

        $line = fgets($this->stream);

        if ($line === false) {
            $this->running = false;
            $this->output->writeln('');
            return;
        }

        $command = trim($line);

        if ($command === '') {
            return;
        }

        if ($this->shouldExit($command)) {
            $this->running = false;
            $this->output->writeln('<info>Goodbye!</info>');
            return;
        }

        $this->executeCommand($command);
    }

    protected function shouldExit(string $command): bool
    {
        return in_array(strtolower($command), self::EXIT_COMMANDS, true);
    }

    protected function executeCommand(string $command): void
    {
        $expression = !$this->isStatement($command);

        [$result, $output, $error] = $this->evaluate(
            $expression ? $this->toExpression($command) : $this->toStatement($command)
        );

        if ($error instanceof \ParseError && $expression) {
            [$result, $output, $error] = $this->evaluate($command);
        }

        if ($output !== '') {
            $this->output->write($output, false, OutputInterface::OUTPUT_RAW);

            if (!str_ends_with($output, "\n")) {
                $this->output->writeln('');
            }
        }

        if ($error !== null) {
            $this->formatter->displayThrowable($error);
            return;
        }

        $this->formatter->displayResult($result);
    }

    protected function isStatement(string $command): bool
    {
        if (!preg_match('/^\s*([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/', $command, $matches)) {
            return false;
        }

        return in_array(strtolower($matches[1]), self::STATEMENT_KEYWORDS, true);
    }

    protected function toExpression(string $command): string
    {
        $command = rtrim($command);

        if (str_ends_with($command, ';')) {
            $command = rtrim(substr($command, 0, -1));
        }

        return 'return ' . $command . ';';
    }

    protected function toStatement(string $command): string
    {
        $command = rtrim($command);

        return str_ends_with($command, ';') ? $command : $command . ';';
    }

    /**
     * @return array{0: mixed, 1: string, 2: \Throwable|null}
     */
    protected function evaluate(string $code): array
    {
        $level = ob_get_level();
        $output = '';
        $error = null;
        $result = null;
        $reporting = error_reporting(E_ALL);

        ob_start();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            $promoted = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_USER_ERROR | E_RECOVERABLE_ERROR;

            if (!($severity & $promoted) || !(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $result = $this->runInSessionScope($code);
        } catch (\Throwable $exception) {
            $error = $exception;
        } finally {
            restore_error_handler();
            error_reporting($reporting);

            while (ob_get_level() > $level) {
                $buffer = ob_get_clean();

                if ($buffer !== false) {
                    $output = $buffer . $output;
                }
            }
        }

        return [$result, $output, $error];
    }

    protected function runInSessionScope(string $code): mixed
    {
        $__code = $code;
        $__variables = $this->variables;

        $closure = function () use ($__code, $__variables) {
            extract($__variables, EXTR_SKIP);

            try {
                return eval($__code);
            } finally {
                $__locals = get_defined_vars();

                foreach (self::INTERNAL_VARIABLES as $internal) {
                    unset($__locals[$internal]);
                }

                $this->variables = $__locals;
            }
        };

        return \Closure::bind($closure, $this)();
    }
}
