# Jest Sample Result

## Native test and coverage command

```bash
cd samples/jest/code
npm test
```

Result: passed and generated `code/clover.xml`.

```text
Test Suites: 1 passed, 1 total
Tests:       1 passed, 1 total
All files | 100 | 100 | 100 | 100
```

## Parity command

```bash
php parity check --config=samples/jest/parity.yaml --format=json
```

Result: passed. Parity found `code/src/sum.js`, matched `code/tests/sum.test.js`, read Jest Clover output using its `path` attribute, and reported `100%` coverage.
