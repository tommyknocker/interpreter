<?php
declare(strict_types=1);

namespace tommyknocker\interpreter\tests;

use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use tommyknocker\interpreter\Interpreter;

final class InterpreterTest extends TestCase
{
    private Interpreter $interpreter;

    protected function setUp(): void
    {
        $this->interpreter = new Interpreter();
    }

    public function testConstants(): void
    {
        $this->assertTrue($this->interpreter->parse('true'));
        $this->assertFalse($this->interpreter->parse('false'));
        $this->assertNull($this->interpreter->parse('null'));
        $this->assertSame(42, $this->interpreter->parse('42'));
        $this->assertSame(2.4, $this->interpreter->parse('2.4'));
        $this->assertSame('hello', $this->interpreter->parse('"hello"'));
        $this->assertSame('hello "world"', $this->interpreter->parse('"hello \"world\""'));
    }

    public function testArrayFunction(): void
    {
        $ast = $this->interpreter->parse('(array, 1, 2, 3)');
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame([1, 2, 3], $result);
    }

    public function testConcatFunction(): void
    {
        $ast = $this->interpreter->parse('(concat, "Hello, ", "world")');
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame('Hello, world', $result);
    }

    public function testGetArgFunction(): void
    {
        $this->interpreter->setArgs(['Alice', 42]);
        $ast1 = $this->interpreter->parse('(getArg, 0)');
        $ast2 = $this->interpreter->parse('(getArg, 1)');
        $this->assertSame('Alice', $this->interpreter->evaluate($ast1));
        $this->assertSame(42, $this->interpreter->evaluate($ast2));
    }

    public function testMapFunction(): void
    {
        $ast = $this->interpreter->parse('(map, (array, "a","b"), (array, 1,2))');
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame(['a'=>1, 'b'=>2], $result);
    }

    public function testJsonFunction(): void
    {
        $ast = $this->interpreter->parse('(json, (array, "x", "y"))');
        $result = $this->interpreter->evaluate($ast);
        $expected = json_encode(['x','y'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->assertSame($expected, $result);
    }

    public function testNestedFunctions(): void
    {
        $this->interpreter->setArgs(['Alice']);
        $code = '(json,
                    (map,
                        (array, "message", "status"),
                        (array,
                            (concat, "Hello, ", (getArg, 0)),
                            true
                        )
                    )
                 )';
        $ast = $this->interpreter->parse($code);
        $result = $this->interpreter->evaluate($ast);
        $expected = json_encode([
            "message" => "Hello, Alice",
            "status" => true
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->assertSame($expected, $result);
    }

    public function testUserFunction(): void
    {
        $this->interpreter->registerFunction('double', fn($x) => $x * 2);
        $ast = $this->interpreter->parse('(double, 5)');
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame(10, $result);
    }

    public function testComplexNestedUserFunction(): void
    {
        $this->interpreter->setArgs([2,4]);
        $this->interpreter->registerFunction('sum', fn(...$args) => array_sum($args));
        $code = '(concat, "Result: ", (sum, (getArg, 0), (getArg, 1)))';
        $ast = $this->interpreter->parse($code);
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame('Result: 6', $result);
    }

    public function testEscapedStrings(): void
    {
        $code = '(concat, "Hello \"world\" and backslash \\\\ test")';
        $ast = $this->interpreter->parse($code);
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame('Hello "world" and backslash \ test', $result);
    }

    public function testUnexpectedTokenThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->interpreter->parse('('); // no function name
    }

    public function testUnknownFunctionThrows(): void
    {
        $ast = ['func'=>'foo','args'=>[]];
        $this->expectException(RuntimeException::class);
        $this->interpreter->evaluate($ast);
    }

    public function testUnknownFunction(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown function: foo');
        $code = '(foo, 1, 2)';
        $ast = $this->interpreter->parse($code);
        $this->interpreter->evaluate($ast);
    }

    public function testUnexpectedEndOfInput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected end of input');
        $code = '(concat, "Hello"';
        $this->interpreter->parse($code);
    }

    public function testEmptyCode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tokenization failed');
        $code = '';
        $this->interpreter->parse($code);
    }

    public function testGetArgOutOfRange(): void
    {
        $this->interpreter->setArgs([1]);
        $code = '(getArg, 10)';
        $ast = $this->interpreter->parse($code);
        $result = $this->interpreter->evaluate($ast);
        $this->assertNull($result);
    }

    public function testInvalidStringQuotes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected end of input');
        $code = '(concat, "Hello)';
        $this->interpreter->parse($code);
    }

    public function testMapMismatchedArrayLengths(): void
    {
        $code = '(map, (array, 1, 2), (array, "a"))';
        $ast = $this->interpreter->parse($code);
        // Supress E_WARNING
        $result = @$this->interpreter->evaluate($ast);
        // array_combine will return false if the array lengths do not match.
        $this->assertFalse($result);
    }

    public function testEmptyArray(): void
    {
        $code = '(array)';
        $ast = $this->interpreter->parse($code);
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame([], $result);
    }

    public function testEmptyStringConcat(): void
    {
        $code = '(concat, "")';
        $ast = $this->interpreter->parse($code);
        $result = $this->interpreter->evaluate($ast);
        $this->assertSame('', $result);
    }

    public function testJsonThrowsOnInvalidData(): void
    {
        $this->expectException(JsonException::class);
        $resource = fopen('php://memory', 'rb'); // resource cannot be JSON-encoded
        $ast = ['func' => 'json', 'args' => [$resource]];
        $this->interpreter->evaluate($ast);
        fclose($resource);
    }
}