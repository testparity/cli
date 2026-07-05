# S007 Success Criteria

| ID | Criterion |
|----|-----------|
| S007-SC-001 | All PSR-4 path-to-FQCN conversions produce correct results: standard Laravel paths (`app/`, `tests/`), deeply nested paths (5+ segments), custom namespace roots, and the default fallback for unrecognized roots. Verified by unit tests covering each configured root and the fallback path. |
| S007-SC-002 | All source-to-test path derivations produce correct results: files at the source root, files in subdirectories, deeply nested files, and files with non-standard extensions. Verified by unit tests with at least 5 distinct path structures. |
| S007-SC-003 | `file_map` overrides take strict precedence over algorithmic derivation. When a file_map entry exists for a source file, the mapped test path is used; when no entry exists, the algorithm runs. Verified by a test that provides both a file_map entry and a source file, confirming the mapped path is chosen. |
| S007-SC-004 | Path normalization via `normalizeRelativePath()` is idempotent: calling it twice on the same input produces the same output as calling it once. Verified by a property-based or parameterized test with at least 10 path variants including backslashes, double slashes, leading/trailing slashes. |
| S007-SC-005 | Multiple structure blocks produce no cross-contamination: a file_map in structure block A does not affect resolution in structure block B. Verified by a test with two structure blocks where only one has a file_map, confirming the other block uses algorithmic derivation. |
