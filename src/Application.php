<?php

namespace Thinker;

use Thinker\Commands\ReplCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('Thinker', '1.0.0');
        
        $this->add(new ReplCommand());
        $this->setDefaultCommand('repl');
    }
}