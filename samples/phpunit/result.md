# PHPUnit Sample Result

## Native test command

```bash
cd samples/phpunit/code
composer test
```

Result: passed.

```text
OK (1 test, 1 assertion)
```

## Coverage command

```bash
cd samples/phpunit/code
XDEBUG_MODE=coverage vendor/bin/phpunit --configuration phpunit.xml --coverage-clover clover.xml --colors=never
```

Result: passed and generated `code/clover.xml`.

## Parity command

```bash
php parity check --config=samples/phpunit/parity.yaml --format=json
```

Result: passed. Parity found `code/src/InvoiceCalculator.php`, matched `code/tests/InvoiceCalculatorTest.php`, and reported `100%` coverage.
