<?php

namespace Thinker\Repl;

use Thinker\Laravel\LaravelBootstrap;
use Thinker\Output\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReplEngine
{
    protected InputInterface $input;
    protected OutputInterface $output;
    protected OutputFormatter $formatter;
    protected LaravelBootstrap $laravel;
    protected bool $running = true;
    protected string $contextCode = '';

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->formatter = new OutputFormatter($output);
        $this->laravel = new LaravelBootstrap();
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
        } catch (\Exception $e) {
            $this->output->writeln('<comment>Could not load Laravel context: ' . $e->getMessage() . '</comment>');
            $this->output->writeln('<comment>Continuing with basic PHP REPL...</comment>');
        }
        
        $this->output->writeln('');
    }

    protected function processCommand(): void
    {
        $this->output->write('<question>>>> </question>');
        
        $handle = fopen('php://stdin', 'r');
        $input = trim(fgets($handle));
        fclose($handle);

        if ($this->shouldExit($input)) {
            $this->running = false;
            $this->output->writeln('<info>Goodbye!</info>');
            return;
        }

        if (empty($input)) {
            return;
        }

        $this->executeCommand($input);
    }

    protected function shouldExit(string $input): bool
    {
        return in_array(strtolower($input), ['exit', 'quit', 'q']);
    }

    protected function executeCommand(string $command): void
    {
        try {
            // Add return statement if the command doesn't have one and isn't a complete statement
            if (!$this->isCompleteStatement($command)) {
                $command = "return $command;";
            }

            // Execute the command with context
            $fullCode = $this->contextCode . "\n" . $command;
            
            // Capture output
            ob_start();
            $result = eval($fullCode);
            $output = ob_get_clean();

            // Update context with the executed code
            $this->contextCode .= "\n" . $command;

            // Display any output that was generated
            if (!empty($output)) {
                $this->output->writeln($output);
            }

            // Display the result
            $this->formatter->displayResult($result);

        } catch (\ParseError $e) {
            $this->formatter->displayError('Parse Error: ' . $e->getMessage());
        } catch (\Error $e) {
            $this->formatter->displayError('Fatal Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->formatter->displayError('Exception: ' . $e->getMessage());
        }
    }

    protected function isCompleteStatement(string $command): bool
    {
        $command = trim($command);
        
        // Check if it's already a complete statement
        if (str_ends_with($command, ';')) {
            return true;
        }

        // Check for control structures, class definitions, etc.
        $keywords = ['if', 'for', 'foreach', 'while', 'function', 'class', 'interface', 'trait'];
        $firstWord = strtolower(explode(' ', $command)[0]);
        
        return in_array($firstWord, $keywords);
    }


}