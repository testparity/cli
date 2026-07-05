# S003 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S003-EC-001 | Coverage file path does not exist on disk | `CoverageReader::read()` returns `[]`. `CoverageReader::readGlobalCoverage()` returns `null`. `PhpUnitXmlCoverageReader::read()` returns the empty structure (all maps empty, `globalPercent` null). No exceptions thrown. |
| S003-EC-002 | Coverage file exists but is not readable (permission denied) | `file_get_contents` returns `false` (suppressed with `@`). Reader returns empty result, same as missing file. No PHP warning emitted. |
| S003-EC-003 | File contents are not valid XML (e.g., truncated, binary, HTML) | `DOMDocument::loadXML()` / `DOMDocument::load()` fails (suppressed with `@`). Reader returns empty result. No PHP warning or notice emitted to stderr. |
| S003-EC-004 | Clover `<file>` element has no `<metrics>` child element | That file entry is skipped entirely (not included in the returned map). Other valid file entries are still processed. |
| S003-EC-005 | Clover `<file>` element has an empty `name=""` attribute | That file entry is skipped (the `continue` guard fires). Other valid file entries are still processed. |
| S003-EC-006 | Clover `<metrics>` reports `statements="0"` and `coveredstatements="0"` | Coverage percentage defaults to `100.0` for that file (avoids division by zero). |
| S003-EC-007 | PHPUnit XML directory exists but does not contain `index.xml` | `PhpUnitXmlCoverageReader::read()` returns the empty structure. No exception. |
| S003-EC-008 | PHPUnit XML `<file>` node in `index.xml` has `href=""` (empty string) | That file node is skipped. Other valid file nodes are processed normally. |
| S003-EC-009 | PHPUnit XML `index.xml` references a per-file XML via `href` but that file does not exist on disk | That file entry is skipped (the `is_file()` check fails). Other valid entries are processed. |
| S003-EC-010 | PHPUnit XML `<line>` element has no `nr` attribute or `nr=""` | That line is skipped. Other lines with valid `nr` attributes are processed normally. |
| S003-EC-011 | PHPUnit XML `<covered>` element has `by=""` (empty string) | That covered entry is ignored. Other covered entries with non-empty `by` attributes on the same line are still recorded. |
| S003-EC-012 | All configured `coverage_xml` paths are missing (no file or directory found) | `CheckCommand::loadCoverageData()` outputs an error message listing all tried paths and returns `null`, causing the command to exit with `FAILURE`. |
| S003-EC-013 | Clover XML contains Windows-style backslash paths (e.g., `C:\project\app\Foo.php`) | All backslashes are normalized to forward slashes before path comparison and key storage. Both the absolute and relative keys use forward slashes. |
| S003-EC-014 | Clover XML contains relative file paths (no leading `/` or drive letter) and no `projectRoot` is provided | The relative path is used as-is for the key (after backslash normalization). No relative key variant is added (since `projectRoot` is null). |
| S003-EC-015 | PHPUnit XML `index.xml` has no `<project source="...">` element or the `source` attribute is empty | The source prefix defaults to empty string. Relative paths are constructed without a prefix directory (e.g., `Models/User.php` instead of `app/Models/User.php`). |
| S003-EC-016 | `coverage_xml` is configured as an array and all entries point to non-existent paths | Same as S003-EC-012: error message lists all tried paths, command returns `FAILURE`. |
