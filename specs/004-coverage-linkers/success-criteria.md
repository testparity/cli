# S004 Success Criteria

| ID | Criterion |
|----|-----------|
| S004-SC-001 | All Pest coverage declaration variants are detected: `->covers()`, `->coversClass()`, file-level `covers()`, single argument, multiple arguments, multiple calls across a file. Verified by unit tests covering each variant. |
| S004-SC-002 | All PHPUnit CoversClass attribute variants are detected: single `#[CoversClass()]`, multiple stacked attributes, `::class` references, string literal arguments, leading-backslash FQCNs. Verified by unit tests covering each variant. |
| S004-SC-003 | Auto-detection correctly routes Pest files to `PestCoversLinker` and PHPUnit files to `PhpAttributeLinker` with zero manual configuration. Verified by passing both file types through a default `CoverageLinkerRegistry`. |
| S004-SC-004 | Zero false positives: non-coverage attributes (`#[Group]`, `#[DataProvider]`), method-level attributes inside class bodies, `covers` appearing in string literals or comments, and files without coverage declarations all produce empty results. Verified by negative test cases. |
| S004-SC-005 | Class reference resolution correctly handles all import styles: use-map lookup, use-with-alias, sub-namespace resolution, global FQCN with leading backslash, namespace-prefixed bare names, single-quoted and double-quoted string literals. Verified by unit tests for `resolveClassReference()` covering each style. |
