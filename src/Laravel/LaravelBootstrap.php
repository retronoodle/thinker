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
    protected ?string $databaseConfigPath = null;

    public function __construct()
    {
        $this->container = new Container();
    }

    public function boot(): Container
    {
        if ($this->isBooted) {
            return $this->container;
        }

        $this->setupEvents();
        $this->setupFilesystem();
        $this->setupDatabase();

        Container::setInstance($this->container);

        $this->isBooted = true;

        return $this->container;
    }

    protected function setupEvents(): void
    {
        $this->container->instance('events', new Dispatcher($this->container));
    }

    protected function setupFilesystem(): void
    {
        $this->container->instance('files', new Filesystem());
    }

    protected function setupDatabase(): void
    {
        $configPath = $this->findDatabaseConfigPath();

        if ($configPath === null) {
            throw new \RuntimeException('No Laravel database configuration found.');
        }

        $config = require $configPath;

        if (!is_array($config) || !isset($config['connections']) || !is_array($config['connections'])) {
            throw new \RuntimeException("Invalid database configuration in {$configPath}.");
        }

        $default = $config['default'] ?? 'default';

        if (!isset($config['connections'][$default])) {
            throw new \RuntimeException("Default database connection [{$default}] is not configured.");
        }

        $capsule = new Capsule($this->container);
        $capsule->addConnection($config['connections'][$default], $default);
        $capsule->getDatabaseManager()->setDefaultConnection($default);
        $capsule->setEventDispatcher($this->container->make('events'));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $this->databaseConfigPath = $configPath;
        $this->container->instance('db', $capsule);
    }

    protected function findDatabaseConfigPath(): ?string
    {
        $directory = getcwd();

        while ($directory !== false && $directory !== '') {
            $candidate = $directory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

            if (is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
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

    public function getDatabaseConfigPath(): ?string
    {
        return $this->databaseConfigPath;
    }
}
