<?php

namespace Thinker\Tests\Laravel;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Thinker\Laravel\LaravelBootstrap;
use Thinker\Tests\TestCase;

class LaravelBootstrapTest extends TestCase
{
    private string $originalDirectory;

    /** @var string[] */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDirectory = getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->originalDirectory);
        Container::setInstance(null);

        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_boot_is_idempotent(): void
    {
        $this->useProjectFixture();

        $bootstrap = new LaravelBootstrap();
        $container = $bootstrap->boot();

        $this->assertTrue($bootstrap->isBooted());
        $this->assertSame($container, $bootstrap->boot());
    }

    public function test_it_installs_the_container_globally(): void
    {
        $this->useProjectFixture();

        $bootstrap = new LaravelBootstrap();
        $container = $bootstrap->boot();

        $this->assertSame($container, Container::getInstance());
    }

    public function test_it_binds_the_core_services(): void
    {
        $this->useProjectFixture();

        $container = (new LaravelBootstrap())->boot();

        $this->assertInstanceOf(Capsule::class, $container->make('db'));
        $this->assertInstanceOf(Dispatcher::class, $container->make('events'));
        $this->assertInstanceOf(Filesystem::class, $container->make('files'));
    }

    public function test_it_attaches_the_event_dispatcher_before_booting_eloquent(): void
    {
        $this->useProjectFixture();

        $container = (new LaravelBootstrap())->boot();
        $events = $container->make('events');

        $this->assertSame($events, $container->make('db')->getEventDispatcher());
        $this->assertSame($events, Eloquent::getEventDispatcher());
    }

    public function test_it_applies_the_default_database_connection(): void
    {
        $this->useProjectFixture();

        $capsule = (new LaravelBootstrap())->boot()->make('db');

        $this->assertSame('sqlite', $capsule->getDatabaseManager()->getDefaultConnection());
    }

    public function test_eloquent_is_ready_to_query_after_boot(): void
    {
        $this->useProjectFixture();

        $capsule = (new LaravelBootstrap())->boot()->make('db');
        $capsule->getConnection()->getSchemaBuilder()->create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        $model = new class extends Eloquent {
            protected $table = 'users';
            public $timestamps = false;
            protected $guarded = [];
        };

        $model::create(['name' => 'Tim']);

        $this->assertSame(1, $model::query()->count());
        $this->assertSame('Tim', $model::query()->first()->name);
    }

    public function test_it_can_boot_without_opening_a_database_connection(): void
    {
        $this->useProjectFixture();

        $container = (new LaravelBootstrap())->boot();

        $this->assertSame([], $container->make('db')->getDatabaseManager()->getConnections());
    }

    public function test_it_requires_a_database_configuration(): void
    {
        chdir($this->makeTemporaryDirectory());

        $bootstrap = new LaravelBootstrap();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No Laravel database configuration found.');

        try {
            $bootstrap->boot();
        } finally {
            $this->assertFalse($bootstrap->isBooted());
        }
    }

    public function test_it_rejects_an_invalid_database_configuration(): void
    {
        $directory = $this->makeTemporaryDirectory();
        mkdir($directory . '/config', 0777, true);
        file_put_contents($directory . '/config/database.php', '<?php return "nope";');
        chdir($directory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid database configuration');

        (new LaravelBootstrap())->boot();
    }

    public function test_it_rejects_an_unknown_default_connection(): void
    {
        $directory = $this->makeTemporaryDirectory();
        mkdir($directory . '/config', 0777, true);
        file_put_contents(
            $directory . '/config/database.php',
            "<?php return ['default' => 'mysql', 'connections' => []];"
        );
        chdir($directory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Default database connection [mysql] is not configured.');

        (new LaravelBootstrap())->boot();
    }

    private function useProjectFixture(): void
    {
        $directory = $this->makeTemporaryDirectory();
        mkdir($directory . '/config', 0777, true);
        file_put_contents(
            $directory . '/config/database.php',
            <<<'PHP'
<?php

return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ],
];
PHP
        );

        chdir($directory);
    }

    private function makeTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/thinker-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
