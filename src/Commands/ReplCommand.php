<?php

namespace Thinker\Commands;

use Thinker\Repl\ReplEngine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReplCommand extends Command
{
    protected static $defaultName = 'repl';
    protected static $defaultDescription = 'Start the interactive PHP REPL';

    protected function configure(): void
    {
        $this->setDescription('Start the interactive PHP REPL with Laravel context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Thinker - Laravel Tinker Replacement</info>');
        $output->writeln('<comment>Type "exit" or press Ctrl+C to quit</comment>');
        $output->writeln('');

        $repl = new ReplEngine($input, $output);
        $repl->run();

        return Command::SUCCESS;
    }
}