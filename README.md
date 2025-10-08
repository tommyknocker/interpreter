# Functional Language Interpreter

This is a simple interpreter for a minimal functional language in PHP.  

It supports:
- function calls,
- constants (numbers, strings, true, false, null),
- user-defined functions,
- nested functions.

---

## Built-in Functions

- `(getArg, <i>)` — gets the i-th input argument
- `(array, ...)` — creates an array from arguments
- `(map, keys, values)` — creates an associative array (like array_combine)
- `(concat, a, b, ...)` — concatenates strings
- `(json, expr)` — serializes expression to JSON

---

## Installation

```bash
git clone https://github.com/tommyknocker/interpreter.git
cd interpreter
composer install
```
or

```bash
composer require tommyknocker/interpreter
```


## Usage Examples

```php
<?php
require_once 'vendor/autoload.php';

use tommyknocker\interpreter\Interpreter;

$interpreter = new Interpreter();
```

### Example 1
```php
$example = <<<CODE
(json,
    (map,
        (array, "message"),
        (array,
            (concat, "Hello, ",
                (getArg, 0)
            )
        )
    )
)
CODE;
$interpreter->setArgs(['world']);
$ast = $interpreter->parse($example);
$result = $interpreter->evaluate($ast);
/**
 * Result
 * {
 *      "message": "Hello, world"
 * }
 */
echo $result . PHP_EOL;
```

### Example 2
```php
$example = <<<CODE
(json,
    (map,
        (array, "message", "count", "active"),
        (array,
            (concat, "Hello, ", (getArg, 0)),
            42,
            true
        )
    )
)
CODE;
$interpreter->setArgs(['world']);
$ast = $interpreter->parse($example);
$result = $interpreter->evaluate($ast);
/**
 * Result
 * {
 *      "message": "Hello, world",
 *      "count": 42,
 *      "active": true
 * }
 */
echo $result . PHP_EOL;
```

### Example 3
```php
$example = <<<CODE
(json,
    (map,
        (array, "title", "description", "rating", "comment"),
        (array,
            (concat, "Report for ", (getArg, 0)),
            (concat, "Summary: ", (getArg, 1)),
            2.4,
            null
        )
    )
)
CODE;
$interpreter->setArgs(['Alice', 'All systems operational']);
$ast = $interpreter->parse($example);
$result = $interpreter->evaluate($ast);
/**
 * Result
 * {
 *      "title": "Report for Alice",
 *      "description": "Summary: All systems operational",
 *      "rating": 2.4,
 *      "comment": null
 * }
 */
echo $result . PHP_EOL;
```

### Example 4 with user-defined function upper
```php
$example = <<<CODE
(json,
    (map,
        (array, "original", "uppercased", "status", "score"),
        (array,
            (getArg, 0),
            (upper, (getArg, 0)),
            true,
            42.24
        )
    )
)
CODE;
$interpreter->registerFunction('upper', fn($args) => strtoupper($args));
$interpreter->setArgs(['hello world']);
$ast = $interpreter->parse($example);
$result = $interpreter->evaluate($ast);
/**
 * Result
 * {
 *      "original": "hello world",
 *      "uppercased": "HELLO WORLD",
 *      "status": true,
 *      "score": 42.24
 * }
 */
echo $result . PHP_EOL;
```

### Example 5 with user-defined function if
```php
$example = <<<CODE
(json,
    (map,
        (array, "message", "status"),
        (array,
            (if,
                (getArg, 0),
                (concat, "Hello, ", (getArg, 1)),
                "Error: user not found"
            ),
            (getArg, 0)
        )
    )
)
CODE;
$interpreter->registerFunction('if', function($condition, $thenExpr, $elseExpr) use ($interpreter) {
    return $interpreter->evaluate($condition) ?
        $interpreter->evaluate($thenExpr) :
        $interpreter->evaluate($elseExpr);
});
$interpreter->setArgs([true, "Alice"]);
$ast = $interpreter->parse($example);
$result = $interpreter->evaluate($ast);
/**
 * Result
 * {
 *      "message": "Hello, Alice",
 *      "status": "true"
 * }
 */
echo $result . PHP_EOL;

$interpreter->setArgs([false, "Alice"]);
$ast = $interpreter->parse($example);
$result = $interpreter->evaluate($ast);
/**
 * Result
 * {
 *      "message": "Error: user not found",
 *      "status": false
 * }
 */
echo $result . PHP_EOL;
```

### Testing

```bash
./vendor/bin/phpunit --bootstrap vendor/autoload.php tests
```