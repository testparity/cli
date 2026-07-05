# S003 Success Criteria

| ID | Criterion |
|----|-----------|
| S003-SC-001 | Given a Clover XML file with known statement counts, `CoverageReader::read()` produces per-file percentages that match `round(100 * coveredstatements / statements, 2)` for every `<file>` element. |
| S003-SC-002 | Given a PHPUnit XML directory with known per-file `<lines percent="...">` values, `PhpUnitXmlCoverageReader::read()` returns `coverage` values that match each file's `percent` attribute exactly. |
| S003-SC-003 | Given a PHPUnit XML directory where specific lines are annotated with `<covered by="TestClass::method"/>`, the `testsByFile` map contains exactly the deduplicated set of test identifiers per file, and `lineCoverage` maps each line number to its correct list of test identifiers. |
| S003-SC-004 | Given a `coverage_xml` config of `['coverage-xml', 'clover.xml', 'cobertura.xml']`, format detection always selects PHPUnit XML when the directory exists with `index.xml`, and otherwise selects the first existing single-file coverage XML candidate. The result is deterministic regardless of filesystem ordering. |
| S003-SC-005 | For every error condition (missing file, malformed XML, permission denied, missing index.xml), the reader returns an empty/null result without throwing exceptions or emitting PHP warnings to stderr. |
| S003-SC-006 | Given a Cobertura XML file with known `line-rate` values, `CoverageReader::read()` and `readGlobalCoverage()` produce percentages that match `round(line-rate * 100, 2)`. |
