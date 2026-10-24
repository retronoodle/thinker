<?php

namespace Thinker\Tests\Commands;

use PHPUnit\Framework\Attributes\DataProvider;
use Thinker\Tests\TestCase;

class ReplCommandTest extends TestCase
{
    public function test_help_prints_help_without_starting_the_repl(): void
    {
        [$exitCode, $stdout] = $this->runThinker(['help']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Description:', $stdout);
        $this->assertStringContainsString('Usage:', $stdout);
        $this->assertStringNotContainsString('>>> ', $stdout);
    }

    public function test_version_prints_the_name_and_version(): void
    {
        [$exitCode, $stdout] = $this->runThinker(['--version']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Thinker 1.0.0', $stdout);
        $this->assertStringNotContainsString('>>> ', $stdout);
    }

    public function test_it_evaluates_piped_input_and_exits_successfully(): void
    {
        [$exitCode, $stdout] = $this->runThinker([], "1 + 1\nquit\n");

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Thinker - Laravel Tinker Replacement', $stdout);
        $this->assertStringContainsString('>>> ', $stdout);
        $this->assertStringContainsString('=> 2', $stdout);
        $this->assertStringContainsString('Goodbye!', $stdout);
    }

    #[DataProvider('exitCommandProvider')]
    public function test_exit_paths_return_success(string $input): void
    {
        [$exitCode, $stdout, $stderr] = $this->runThinker([], $input);

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('Warning', $stderr);
        $this->assertStringNotContainsString('Fatal error', $stderr);
        $this->assertStringContainsString('>>> ', $stdout);
    }

    public static function exitCommandProvider(): array
    {
        return [
            'exit' => ["exit\n"],
            'quit' => ["quit\n"],
            'q' => ["q\n"],
            'end of input' => [''],
            'end of input after a command' => ["1 + 1\n"],
        ];
    }

    /**
     * @param string[] $arguments
     * @return array{0: int, 1: string, 2: string}
     */
    private function runThinker(array $arguments = [], string $stdin = ''): array
    {
        $binary = dirname(__DIR__, 2) . '/bin/thinker';
        $command = array_merge([PHP_BINARY, $binary], $arguments);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));

        $this->assertIsResource($process);

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }
}
