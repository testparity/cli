#!/usr/bin/env bash

set -euo pipefail

TARGET_DIR="${1:-}"
if [[ -z "$TARGET_DIR" ]]; then
    echo "Usage: $0 <target-directory>" >&2
    exit 1
fi

mkdir -p "$TARGET_DIR/src" "$TARGET_DIR/tests"

cat > "$TARGET_DIR/src/InvoiceTotal.php" <<'PHP'
<?php

final class InvoiceTotal
{
    public function cents(array $lines): int
    {
        return array_sum($lines);
    }
}
PHP

cat > "$TARGET_DIR/tests/InvoiceTotalTest.php" <<'PHP'
<?php

final class InvoiceTotalTest
{
    public function coversPrimaryBehavior(): void
    {
    }
}
PHP

cat > "$TARGET_DIR/parity-coverage.json" <<'JSON'
{
  "version": 1,
  "globalPercent": 100,
  "files": [
    {
      "path": "src/InvoiceTotal.php",
      "coveragePercent": 100,
      "totalExecutableLines": 3,
      "lines": [
        { "line": 5, "coveredBy": ["InvoiceTotalTest::coversPrimaryBehavior"] },
        { "line": 6, "coveredBy": ["InvoiceTotalTest::coversPrimaryBehavior"] },
        { "line": 7, "coveredBy": ["InvoiceTotalTest::coversPrimaryBehavior"] }
      ]
    }
  ]
}
JSON

cat > "$TARGET_DIR/emit-coverage.php" <<'PHP'
<?php

declare(strict_types=1);

$target = $argv[1] ?? null;
if (! is_string($target) || $target === '' || ! copy(__DIR__.'/parity-coverage.json', $target)) {
    fwrite(STDERR, "Could not write coverage artifact\n");
    exit(1);
}
PHP

cat > "$TARGET_DIR/parity.yaml" <<'YAML'
settings:
  namespace_roots:
    src: App
    tests: Tests
  source_extension: ".php"
  test_suffix: "Test"
  test_extension: ".php"
  namespace_separator: "\\"

coverage_xml: [parity-coverage.json, coverage-xml, clover.xml, cobertura.xml]
min_coverage: 90
min_coverage_global: 90
min_matched_coverage: 90

test:
  command: "php emit-coverage.php {coverage}"
  coverage: ".parity/tmp/{slug}.json"
  reports: ".parity/per-test"
  timeout: 30

structure:
  - name: "Smoke"
    paths:
      source: "src"
      test: "tests"
    rules:
      - minimum-coverage:
          min: 90
      - matched-coverage:
          min: 90
      - coverage-attribution
YAML
