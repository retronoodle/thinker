<?php

namespace Thinker\Laravel;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;

class LaravelBootstrap
{
    protected Container $container;
    protected bool $isBooted = false;

    public function __construct()
    {
        $this->container = new Container();
    }

    public function boot(): Container
    {
        if ($this->isBooted) {
            return $this->container;
        }

        $this->setupContainer();
        $this->setupDatabase();
        $this->setupEvents();
        $this->setupFilesystem();

        $this->isBooted = true;

        return $this->container;
    }

    protected function setupContainer(): void
    {
        Container::setInstance($this->container);
    }

    protected function setupDatabase(): void
    {
        $capsule = new Capsule();
        
        // Try to load Laravel config if available
        $configPath = $this->findLaravelConfigPath();
        if ($configPath && file_exists($configPath . '/database.php')) {
            $config = require $configPath . '/database.php';
            $defaultConnection = $config['default'] ?? 'mysql';
            
            if (isset($config['connections'][$defaultConnection])) {
                $capsule->addConnection($config['connections'][$defaultConnection]);
            }
        }

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $this->container->instance('db', $capsule);
    }

    protected function setupEvents(): void
    {
        $events = new Dispatcher($this->container);
        $this->container->instance('events', $events);
    }

    protected function setupFilesystem(): void
    {
        $filesystem = new Filesystem();
        $this->container->instance('files', $filesystem);
    }

    protected function findLaravelConfigPath(): ?string
    {
        $currentPath = getcwd();
        
        // Look for Laravel application structure
        $paths = [
            $currentPath . '/config',
            $currentPath . '/../config',
            $currentPath . '/../../config'
        ];

        foreach ($paths as $path) {
            if (is_dir($path) && file_exists($path . '/app.php')) {
                return $path;
            }
        }

        return null;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function isBooted(): bool
    {
        return $this->isBooted;
    }
}