<?php

namespace Thinker\Tests\Repl;

use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;
use Thinker\Laravel\LaravelBootstrap;
use Thinker\Repl\ReplEngine;
use Thinker\Tests\TestCase;

class ReplEngineTest extends TestCase
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

        foreach ($this->temporaryDirectories as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function test_it_reads_a_line_and_prints_the_evaluated_result(): void
    {
        $output = $this->runEngine("1 + 1\nquit\n");

        $this->assertStringContainsString('>>> ', $output);
        $this->assertStringContainsString('=> 2', $output);
    }

    public function test_it_preserves_variables_between_commands(): void
    {
        $output = $this->runEngine("\$name = \"World\"\n\"Hello, \$name!\"\nquit\n");

        $this->assertStringContainsString('=> "Hello, World!"', $output);
    }

    public function test_it_prints_the_assigned_value_for_an_assignment_statement(): void
    {
        $output = $this->runEngine("\$total = 40 + 2;\nquit\n");

        $this->assertStringContainsString('=> 42', $output);
    }

    public function test_it_evaluates_multiple_expressions_in_sequence(): void
    {
        $output = $this->runEngine("1 + 1\n2 + 2\nquit\n");

        $this->assertStringContainsString('=> 2', $output);
        $this->assertStringContainsString('=> 4', $output);
        $this->assertSame(1, substr_count($output, '=> 2'));
    }

    public function test_it_executes_keyword_statements_without_a_trailing_semicolon(): void
    {
        $output = $this->runEngine("\$x = 1;\nunset(\$x)\nisset(\$x)\nquit\n");

        $this->assertStringContainsString('=> false', $output);
    }

    public function test_it_ignores_blank_and_whitespace_only_input(): void
    {
        $output = $this->runEngine("1 + 1\n\n   \n\t\nquit\n");

        $this->assertSame(1, substr_count($output, '=> '));
        $this->assertStringNotContainsString('Parse Error', $output);
        $this->assertStringNotContainsString('Fatal Error', $output);
    }

    #[DataProvider('exitCommandProvider')]
    public function test_typed_exit_commands_end_the_session_cleanly(string $command): void
    {
        $output = $this->runEngine($command . "\n");

        $this->assertStringContainsString('Goodbye!', $output);
        $this->assertStringNotContainsString('=> ', $output);
    }

    public static function exitCommandProvider(): array
    {
        return [
            'exit' => ['exit'],
            'quit' => ['quit'],
            'q' => ['q'],
            'uppercase quit' => ['QUIT'],
        ];
    }

    public function test_end_of_input_ends_the_session_without_an_error(): void
    {
        $output = $this->runEngine("1 + 1\n");

        $this->assertStringContainsString('=> 2', $output);
        $this->assertStringNotContainsString('Goodbye!', $output);
        $this->assertStringNotContainsString('Warning', $output);
        $this->assertStringNotContainsString('Fatal Error', $output);
    }

    public function test_it_recovers_after_a_parse_error(): void
    {
        $output = $this->runEngine("foo(\n1 + 1\nquit\n");

        $this->assertStringContainsString('Parse Error:', $output);
        $this->assertStringContainsString('=> 2', $output);
    }

    public function test_it_recovers_after_an_undefined_variable_error(): void
    {
        $output = $this->runEngine("\$missing + 1\n1 + 1\nquit\n");

        $this->assertStringContainsString('Fatal Error: Undefined variable $missing', $output);
        $this->assertStringContainsString('=> 2', $output);
    }

    public function test_it_recovers_after_a_thrown_exception(): void
    {
        $output = $this->runEngine("throw new RuntimeException(\"boom\")\n1 + 1\nquit\n");

        $this->assertStringContainsString('Exception: boom', $output);
        $this->assertStringContainsString('=> 2', $output);
    }

    public function test_it_prints_captured_output_before_the_result(): void
    {
        $output = $this->runEngine("echo \"hi\"; return 5;\nquit\n");

        $this->assertStringContainsString("hi\n=> 5", $output);
    }

    public function test_only_user_variables_persist_in_the_session(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\$user = 1;\nquit\n");
        rewind($stream);

        $engine = new ReplEngine($stream, new BufferedOutput(), $this->fakeLaravel());
        $engine->run();
        fclose($stream);

        $property = new \ReflectionProperty(ReplEngine::class, 'variables');

        $this->assertSame(['user' => 1], $property->getValue($engine));
    }

    public function test_it_continues_as_a_plain_php_shell_when_bootstrap_fails(): void
    {
        $directory = $this->makeTemporaryDirectory();
        chdir($directory);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "1 + 1\nquit\n");
        rewind($stream);

        $output = new BufferedOutput();
        (new ReplEngine($stream, $output))->run();
        fclose($stream);

        $rendered = $output->fetch();

        $this->assertStringContainsString('Could not load Laravel context:', $rendered);
        $this->assertStringContainsString('Continuing with basic PHP REPL...', $rendered);
        $this->assertStringContainsString('=> 2', $rendered);
    }

    private function runEngine(string $input): string
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $input);
        rewind($stream);

        $output = new BufferedOutput();
        (new ReplEngine($stream, $output, $this->fakeLaravel()))->run();
        fclose($stream);

        return $output->fetch();
    }

    private function fakeLaravel(): LaravelBootstrap
    {
        return new class extends LaravelBootstrap {
            public function boot(): Container
            {
                return $this->getContainer();
            }
        };
    }

    private function makeTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/thinker-repl-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }
}
