# Cargo/Rust Sample Result

## Native test command

```bash
cd samples/cargo/code
cargo test
```

Result: passed.

```text
test doubles_values ... ok
test result: ok. 1 passed
```

## Coverage fixture

Rust's built-in `cargo test` does not emit Clover or Cobertura coverage by itself. This sample commits a small Cobertura fixture at `code/cobertura.xml`, matching what tools such as `cargo-llvm-cov` can produce.

## Parity command

```bash
php parity check --config=samples/cargo/parity.yaml --format=json
```

Result: passed. Parity found `code/src/lib.rs`, matched `code/tests/lib_test.rs`, read Cobertura coverage, and reported `100%` coverage.
