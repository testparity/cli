# Mocha Sample Result

## Native test command

```bash
cd samples/mocha/code
npm test
```

Result: passed.

```text
1 passing
```

## Coverage command

```bash
cd samples/mocha/code
npm run coverage
```

Result: passed and generated `code/clover.xml`.

```text
All files | 100 | 100 | 100 | 100
```

## Parity command

```bash
php parity check --config=samples/mocha/parity.yaml --format=json
```

Result: passed. Parity found `code/src/greeting.js`, matched `code/tests/greeting.spec.js`, read NYC Clover output using its `path` attribute, and reported `100%` coverage.
