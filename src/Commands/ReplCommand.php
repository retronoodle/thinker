<?php

namespace Thinker\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thinker\Repl\ReplEngine;

#[AsCommand(name: 'repl', description: 'Start the interactive PHP REPL with Laravel context')]
class ReplCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Start the interactive PHP REPL with Laravel context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Thinker - Laravel Tinker Replacement</info>');
        $output->writeln('<comment>Type "exit" or press Ctrl+C to quit</comment>');
        $output->writeln('');

        $repl = new ReplEngine(STDIN, $output);
        $repl->run();

        return Command::SUCCESS;
    }
}
