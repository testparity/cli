# Pest Sample Result

## Native test command

```bash
cd samples/pest/code
composer test
```

Result: passed.

```text
Tests: 1 passed (1 assertions)
```

## Coverage command

```bash
cd samples/pest/code
XDEBUG_MODE=coverage vendor/bin/pest --coverage-clover clover.xml --colors=never
```

Result: passed and generated `code/clover.xml`.

## Parity command

```bash
php parity check --config=samples/pest/parity.yaml --format=json
```

Result: passed. Parity found `code/src/Slugger.php`, matched `code/tests/SluggerTest.php`, verified the Pest `->covers(Slugger::class)` declaration, and reported `100%` coverage.
