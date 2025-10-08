<?php
declare(strict_types=1);

namespace tommyknocker\interpreter;

use RuntimeException;

/**
 * Functional language interpreter
 *
 * Syntax:
 * <program> ::= <expression>
 * <expression> ::= <function_call> | <constant>
 * <function_call> ::= '(' <function_name> ')' | '(' <function_name> ',' <function_args> ')'
 * <constant> ::= 'true' | 'false' | 'null' | <string> | <number>
 *
 * Supported functions:
 * - (getArg, <i>) — gets the i-th input argument
 * - (array, ...) — creates an array from arguments
 * - (map, keys, values) — creates an associative array (like array_combine)
 * - (concat, a, b, ...) — concatenates strings
 * - (json, expr) — serializes expression to JSON
 *
 * User-defined functions can be added via registerFunction()
 */
class Interpreter
{
    /** Built-in functions */
    protected array $builtins;

    /** User-defined functions */
    protected array $userFunctions = [];

    /** Input arguments */
    protected array $args = [];

    public function __construct()
    {
        // Basic built-in functions
        $this->builtins = [
            'array' => fn(...$args) => $args,
            'concat' => fn(...$args) => implode('', array_map('strval', $args)),
            'getArg' => fn(int $i) => $this->args[$i] ?? null,
            'map' => fn(array $keys, array $values) => count($keys) === count($values) ? array_combine($keys, $values) : false,
            'json' => fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Set input arguments
     * @param array $args
     * @return void
     */
    public function setArgs(array $args): void
    {
        $this->args = $args;
    }

    /**
     * Register a user-defined function
     * @param string $name
     * @param callable $fn
     * @return void
     */
    public function registerFunction(string $name, callable $fn): void
    {
        $this->userFunctions[$name] = $fn;
    }

    /**
     * Evaluate AST (Abstract Syntax Tree)
     * @param mixed $ast
     * @return mixed
     */
    public function evaluate(mixed $ast): mixed
    {
        // Function
        if (is_array($ast) && isset($ast['func'])) {
            $funcName = $ast['func'];
            $args = array_map(fn($a) => $this->evaluate($a), $ast['args']);

            if (isset($this->builtins[$funcName])) {
                return ($this->builtins[$funcName])(...$args);
            }

            if (isset($this->userFunctions[$funcName])) {
                return ($this->userFunctions[$funcName])(...$args);
            }

            throw new RuntimeException("Unknown function: $funcName");
        }
        // Constant, string
        return $ast;
    }

    /**
     * Parser
     * @param string $code
     * @return mixed
     */
    public function parse(string $code): mixed
    {
        $tokens = $this->tokenize($code);
        $pos = 0;
        return $this->parseExpr($tokens, $pos);
    }

    /**
     * Tokenization
     * @param string $code
     * @return array
     */
    protected function tokenize(string $code): array
    {
        // Remove extra spaces and line breaks
        $code = preg_replace('/\s+/', ' ', $code);

        // Tokens: parentheses, commas, strings, numbers, words
        // \(|\)|, - parentheses and commas
        // "(?:[^"\\\\]|\\\\.)*" - strings, non-capturing group. String in quotes "...", inside can be any
        //                         character except quotes or backslash, or a backslash followed by any character
        //                         Examples: "Hello, world", "Hello \n\tWorld", "C:\\path\\file.txt".
        // \d+\.\d+ - float numbers
        // \d+ - integer numbers
        // \w+ - function names, words
        preg_match_all('/\(|\)|,|"(?:[^"\\\\]|\\\\.)*"|\d+\.\d+|\d+|\w+/', $code, $matches);

        $tokens = $matches[0] ?? [];

        if (empty($tokens)) {
            throw new RuntimeException("Tokenization failed");
        }

        // Process all tokens and handle strings
        $tokens = array_map(static function($token) {
            // If token is a quoted string
            if (str_starts_with($token, '"') && str_ends_with($token, '"')) {
                // 1. Remove surrounding quotes
                $string = substr($token, 1, -1);
                // 2. Decode only \" and \\
                return str_replace(['\\"', '\\\\'], ['"', '\\'], $string);
            }
            return $token;
        }, $tokens);

        // Check for unclosed quotes
        $quotesCount = substr_count($code, '"');
        if ($quotesCount % 2 !== 0) {
            throw new RuntimeException("Unexpected end of input");
        }

        return $tokens;
    }

    /**
     * Recursive expression parser
     * @param array $tokens
     * @param int $pos
     * @return mixed
     */
    protected function parseExpr(array &$tokens, int &$pos): mixed
    {
        $token = $tokens[$pos++] ?? null;
        if ($token === null) {
            throw new RuntimeException("Unexpected end of input");
        }

        // Function call
        if ($token === '(') {
            $funcName = $tokens[$pos++] ?? throw new RuntimeException("Expected function name");
            $args = [];

            while (($tokens[$pos] ?? null) !== ')') {
                // Skip commas
                if (($tokens[$pos] ?? null) === ',') {
                    $pos++;
                    continue;
                }
                $args[] = $this->parseExpr($tokens, $pos);
            }
            $pos++; // Skip closing ')'
            return ['func' => $funcName, 'args' => $args];
        }

        if (!is_string($token)) {
            throw new RuntimeException("Unexpected token type: " . gettype($token));
        }

        // Constants
        return match (true) {
            $token === 'true' => true,
            $token === 'false' => false,
            $token === 'null' => null,
            str_starts_with($token, '"') && str_ends_with($token, '"') => substr($token, 1, -1),
            is_numeric($token) => str_contains($token, '.') ? (float)$token : (int)$token,
            default => $token
        };
    }
}