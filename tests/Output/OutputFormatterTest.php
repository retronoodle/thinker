<?php

namespace Thinker\Tests\Output;

use Symfony\Component\Console\Output\BufferedOutput;
use Thinker\Output\OutputFormatter;
use Thinker\Repl\ReplEngine;
use Thinker\Laravel\LaravelBootstrap;
use Illuminate\Container\Container;
use Thinker\Tests\TestCase;

class OutputFormatterTest extends TestCase
{
    public function test_it_renders_null(): void
    {
        $output = $this->display(null);

        $this->assertSame("=> null\n", $output);
    }

    public function test_it_renders_booleans(): void
    {
        $this->assertSame("=> false\n", $this->display(false));
        $this->assertSame("=> true\n", $this->display(true));
    }

    public function test_it_renders_numbers(): void
    {
        $this->assertSame("=> 42\n", $this->display(42));
        $this->assertSame("=> 3.5\n", $this->display(3.5));
    }

    public function test_it_quotes_strings_and_escapes_quotes_and_backslashes(): void
    {
        $output = $this->display('She said "hi" \ bye');

        $this->assertSame("=> \"She said \\\"hi\\\" \\\\ bye\"\n", $output);
    }

    public function test_it_renders_an_empty_array(): void
    {
        $this->assertSame("=> []\n", $this->display([]));
    }

    public function test_it_renders_an_indexed_array(): void
    {
        $expected = <<<'OUTPUT'
=> [
  0 => 1
  1 => 2
  2 => 3
]
OUTPUT;

        $this->assertSame($expected . "\n", $this->display([1, 2, 3]));
    }

    public function test_it_renders_an_associative_array(): void
    {
        $expected = <<<'OUTPUT'
=> [
  "name" => "Tim"
]
OUTPUT;

        $this->assertSame($expected . "\n", $this->display(['name' => 'Tim']));
    }

    public function test_it_renders_nested_arrays_with_indentation(): void
    {
        $expected = <<<'OUTPUT'
=> [
  "rows" => [
    0 => 1
    1 => 2
  ]
]
OUTPUT;

        $this->assertSame($expected . "\n", $this->display(['rows' => [1, 2]]));
    }

    public function test_it_renders_an_object_with_to_array(): void
    {
        $object = new class {
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };

        $output = $this->display($object);

        $this->assertStringContainsString('class@anonymous', $output);
        $this->assertStringContainsString('"id" => 1', $output);
    }

    public function test_it_renders_an_object_without_to_array_by_class_name(): void
    {
        $this->assertSame("=> stdClass\n", $this->display(new \stdClass()));
    }

    public function test_it_renders_parse_errors(): void
    {
        $output = $this->displayThrowable(new \ParseError('unexpected token'));

        $this->assertSame("Parse Error: unexpected token\n", $output);
    }

    public function test_it_renders_runtime_errors(): void
    {
        $output = $this->displayThrowable(new \Error('call to a member function on null'));

        $this->assertSame("Fatal Error: call to a member function on null\n", $output);
    }

    public function test_it_renders_exceptions(): void
    {
        $output = $this->displayThrowable(new \RuntimeException('something broke'));

        $this->assertSame("Exception: something broke\n", $output);
    }

    public function test_it_prints_captured_output_before_the_result(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "echo \"hi\"; return 5;\nquit\n");
        rewind($stream);

        $buffer = new BufferedOutput();
        $laravel = new class extends LaravelBootstrap {
            public function boot(): Container
            {
                return $this->getContainer();
            }
        };

        (new ReplEngine($stream, $buffer, $laravel))->run();
        fclose($stream);

        $this->assertStringContainsString("hi\n=> 5", $buffer->fetch());
    }

    private function display(mixed $value): string
    {
        $output = new BufferedOutput();
        (new OutputFormatter($output))->displayResult($value);

        return $output->fetch();
    }

    private function displayThrowable(\Throwable $exception): string
    {
        $output = new BufferedOutput();
        (new OutputFormatter($output))->displayThrowable($exception);

        return $output->fetch();
    }
}
