<?php

namespace Thinker\Output;

use Symfony\Component\Console\Formatter\OutputFormatter as ConsoleFormatter;
use Symfony\Component\Console\Output\OutputInterface;

class OutputFormatter
{
    protected OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function displayResult(mixed $result): void
    {
        $rendered = ConsoleFormatter::escape($this->format($result));

        $this->output->writeln("<info>=> $rendered</info>");
    }

    public function displayError(string $message): void
    {
        $rendered = ConsoleFormatter::escape($message);

        $this->output->writeln("<error>$rendered</error>");
    }

    public function displayThrowable(\Throwable $exception): void
    {
        $this->displayError($this->categorize($exception) . ': ' . $exception->getMessage());
    }

    public function categorize(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \ParseError => 'Parse Error',
            $exception instanceof \Error,
            $exception instanceof \ErrorException => 'Fatal Error',
            default => 'Exception',
        };
    }

    public function format(mixed $value): string
    {
        return $this->render($value, 0);
    }

    protected function render(mixed $value, int $depth): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => $this->renderFloat($value),
            is_string($value) => '"' . $this->escapeString($value) . '"',
            is_array($value) => $this->renderArray($value, $depth),
            is_object($value) => $this->renderObject($value, $depth),
            default => get_debug_type($value),
        };
    }

    protected function renderFloat(float $value): string
    {
        if (is_nan($value)) {
            return 'NAN';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'INF' : '-INF';
        }

        return (string) $value;
    }

    protected function renderArray(array $array, int $depth): string
    {
        if ($array === []) {
            return '[]';
        }

        $indent = str_repeat('  ', $depth);
        $childIndent = $indent . '  ';
        $lines = ['['];

        foreach ($array as $key => $value) {
            $lines[] = $childIndent . $this->renderKey($key) . ' => ' . $this->render($value, $depth + 1);
        }

        $lines[] = $indent . ']';

        return implode("\n", $lines);
    }

    protected function renderObject(object $object, int $depth): string
    {
        $className = $this->className($object);

        if (!method_exists($object, 'toArray')) {
            return $className;
        }

        $array = $object->toArray();

        if (!is_array($array) || $array === []) {
            return $className . ' {}';
        }

        $indent = str_repeat('  ', $depth);
        $childIndent = $indent . '  ';
        $lines = [$className . ' {'];

        foreach ($array as $key => $value) {
            $lines[] = $childIndent . $this->renderKey($key) . ' => ' . $this->render($value, $depth + 1);
        }

        $lines[] = $indent . '}';

        return implode("\n", $lines);
    }

    protected function renderKey(int|string $key): string
    {
        return is_string($key) ? '"' . $this->escapeString($key) . '"' : (string) $key;
    }

    protected function className(object $object): string
    {
        $className = get_class($object);
        $position = strpos($className, "\0");

        return $position === false ? $className : substr($className, 0, $position);
    }

    protected function escapeString(string $string): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $string);
    }
}
