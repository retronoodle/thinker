<?php

namespace Thinker\Output;

use Symfony\Component\Console\Output\OutputInterface;

class OutputFormatter
{
    protected OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function displayResult($result): void
    {
        if ($result === null) {
            $this->output->writeln('<comment>=> null</comment>');
            return;
        }

        if (is_bool($result)) {
            $value = $result ? 'true' : 'false';
            $this->output->writeln("<info>=> $value</info>");
            return;
        }

        if (is_string($result)) {
            $escaped = $this->escapeString($result);
            $this->output->writeln("<info>=> \"$escaped\"</info>");
            return;
        }

        if (is_numeric($result)) {
            $this->output->writeln("<info>=> $result</info>");
            return;
        }

        if (is_array($result)) {
            $this->displayArray($result);
            return;
        }

        if (is_object($result)) {
            $this->displayObject($result);
            return;
        }

        // Fallback for other types
        $this->output->writeln('<info>=> ' . var_export($result, true) . '</info>');
    }

    public function displayError(string $message): void
    {
        $this->output->writeln("<error>$message</error>");
    }

    protected function displayArray(array $array, int $depth = 0): void
    {
        $indent = str_repeat('  ', $depth);
        
        if (empty($array)) {
            $this->output->writeln('<info>=> []</info>');
            return;
        }

        if ($depth === 0) {
            $this->output->writeln('<info>=> [</info>');
        } else {
            $this->output->writeln('[');
        }

        foreach ($array as $key => $value) {
            $keyDisplay = is_string($key) ? "\"$key\"" : $key;
            $this->output->write("$indent  $keyDisplay => ");

            if (is_array($value)) {
                $this->displayArray($value, $depth + 1);
            } elseif (is_object($value)) {
                $className = get_class($value);
                $this->output->writeln("<comment>$className</comment>");
            } elseif (is_string($value)) {
                $escaped = $this->escapeString($value);
                $this->output->writeln("\"$escaped\"");
            } elseif (is_bool($value)) {
                $boolValue = $value ? 'true' : 'false';
                $this->output->writeln($boolValue);
            } elseif ($value === null) {
                $this->output->writeln('<comment>null</comment>');
            } else {
                $this->output->writeln($value);
            }
        }

        $this->output->writeln("$indent]" . ($depth === 0 ? '</info>' : ''));
    }

    protected function displayObject(object $object): void
    {
        $className = get_class($object);
        
        if (method_exists($object, 'toArray')) {
            $this->output->writeln("<info>=> $className {</info>");
            $array = $object->toArray();
            foreach ($array as $key => $value) {
                $this->output->write("  $key: ");
                if (is_string($value)) {
                    $escaped = $this->escapeString($value);
                    $this->output->writeln("\"$escaped\"");
                } elseif (is_bool($value)) {
                    $boolValue = $value ? 'true' : 'false';
                    $this->output->writeln($boolValue);
                } elseif ($value === null) {
                    $this->output->writeln('<comment>null</comment>');
                } else {
                    $this->output->writeln($value);
                }
            }
            $this->output->writeln('<info>}</info>');
        } else {
            $this->output->writeln("<info>=> $className</info>");
        }
    }

    protected function escapeString(string $string): string
    {
        return addslashes($string);
    }
}