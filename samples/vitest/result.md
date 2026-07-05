# Vitest Sample Result

## Native test command

```bash
cd samples/vitest/code
npm test
```

Result: passed.

```text
Test Files  1 passed (1)
Tests       1 passed (1)
```

## Coverage command

```bash
cd samples/vitest/code
npm run coverage
```

Result: passed and copied `coverage/clover.xml` to `code/clover.xml`.

```text
All files | 100 | 100 | 100 | 100
```

## Parity command

```bash
php parity check --config=samples/vitest/parity.yaml --format=json
```

Result: passed. Parity found `code/src/formatCurrency.ts`, matched `code/tests/formatCurrency.test.ts`, read Vitest Clover output using its `path` attribute, and reported `100%` coverage.
