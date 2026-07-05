# S003 Functional Requirements

### Clover XML Reading

S003-FR-001 [P1] The `CoverageReader` **MUST** parse a Clover XML file and return a map of file paths to coverage percentages (0--100, rounded to 2 decimal places).
S003-FR-001.a Coverage percentage **MUST** be computed as `(coveredstatements / statements) * 100`.
S003-FR-001.b The reader **MUST** extract `statements` and `coveredstatements` from the `<metrics>` child element of each `<file>` element.
S003-FR-001.c When multiple `<metrics>` elements exist under a single `<file>`, the reader **MUST** use the last one.

S003-FR-002 [P1] The `CoverageReader` **MUST** provide a method to read the project-level (global) coverage percentage from Clover XML.
S003-FR-002.a Global coverage **MUST** be read from the `<metrics>` element that has a `files` attribute (the project-level totals node).
S003-FR-002.b Global coverage **MUST** be computed as `(coveredstatements / statements) * 100`, rounded to 2 decimal places.
S003-FR-002.c If the project-level metrics element is not found, the method **MUST** return `null`.

S003-FR-003 [P1] When a `projectRoot` parameter is provided, the reader **MUST** produce both absolute and relative path keys for each file entry.
S003-FR-003.a Absolute paths **MUST** be resolved via `realpath()` when the path exists on disk; otherwise the normalized string path is used.
S003-FR-003.b Relative paths **MUST** be computed by stripping the `projectRoot` prefix from the absolute path.
S003-FR-003.c If the file path in the XML is already relative (no leading `/` or drive letter), the reader **MUST** use it as the relative key directly.
S003-FR-003.d All backslashes **MUST** be normalized to forward slashes before any path comparison.

### PHPUnit XML Reading

S003-FR-004 [P1] The `PhpUnitXmlCoverageReader` **MUST** read a PHPUnit XML coverage directory by locating `index.xml` within the given directory path.
S003-FR-004.a The reader **MUST** parse the namespace `https://schema.phpunit.de/coverage/1.0` and fall back to local-name queries when namespace resolution fails.

S003-FR-005 [P1] The reader **MUST** return per-file coverage percentages by reading the `percent` attribute from the `<totals><lines>` element of each per-file XML document.

S003-FR-006 [P1] The reader **MUST** return per-test attribution data as a map of file paths to lists of test method identifiers (e.g., `Tests\Unit\FooTest::test_something`).
S003-FR-006.a Test identifiers **MUST** be extracted from `<covered by="..."/>` elements nested under `<coverage><line>` elements.
S003-FR-006.b Each unique test identifier **MUST** appear at most once in the list for a given file (deduplicated across lines).

S003-FR-007 [P1] The reader **MUST** return per-line coverage data as a map of file paths to `array<int, list<string>>`, where the int key is the line number and the list contains test method identifiers that cover that line.

S003-FR-008 [P1] The reader **MUST** return the total executable line count per file, read from the `executable` attribute of the `<totals><lines>` element.

S003-FR-009 [P1] The reader **MUST** return a global coverage percentage read from the root `<directory name="/">` element's `<totals><lines percent="...">` in `index.xml`.
S003-FR-009.a If the root directory element or its lines element is not found, `globalPercent` **MUST** be `null`.

### Format Detection

S003-FR-010 [P1] Format detection **MUST** be path-type based: if the resolved path is a regular file, single-file XML coverage is read by `CoverageReader`; if it is a directory containing `index.xml`, PHPUnit XML is assumed.
S003-FR-010.a The detection **MUST NOT** inspect file contents to determine format; path structure alone is sufficient.

S003-FR-011 [P1] When `coverage_xml` is configured as an array, the system **MUST** iterate candidates in order and use the first path that resolves to an existing file or directory (with `index.xml`).
S003-FR-011.a Each candidate path **MUST** be resolved relative to the project root.
S003-FR-011.b If no candidate resolves to an existing file or directory, the system **MUST** report an error listing all tried paths.

### Error Handling

S003-FR-012 [P1] If the coverage file or directory does not exist, the reader **MUST** return an empty result (empty map for `CoverageReader`, empty structure for `PhpUnitXmlCoverageReader`).
S003-FR-012.a The reader **MUST NOT** throw an exception for missing files.

S003-FR-013 [P1] If the XML content is malformed (not parseable by `DOMDocument`), the reader **MUST** return an empty result.
S003-FR-013.a The reader **MUST NOT** emit PHP warnings or notices to stderr for malformed XML (suppressed via `@` operator on `loadXML`/`load`).

### Path Construction

S003-FR-014 [P2] The PHPUnit XML reader **MUST** extract the `source` attribute from the `<project>` element in `index.xml` and use its basename as a prefix for all relative file paths.
S003-FR-014.a If the `<project>` element or its `source` attribute is missing, the prefix **MUST** default to an empty string (no prefix).

S003-FR-015 [P2] When `projectRoot` is provided to `PhpUnitXmlCoverageReader`, the reader **MUST** store coverage data under both the relative path key and the absolute (normalized) path key.
S003-FR-015.a This dual-key storage enables lookup by either relative path (from config) or absolute path (from `realpath()`).

### Coverage Edge Values

S003-FR-016 [P1] When a file has zero statements, the coverage percentage **MUST** default to `100.0`.
S003-FR-016.a This applies to `CoverageReader` only (Clover XML). PHPUnit XML stores the percentage directly in the `percent` attribute.

### Cobertura XML Reading

S003-FR-017 [P1] The `CoverageReader` **MUST** parse Cobertura XML files and return a map of file paths to coverage percentages (0--100, rounded to 2 decimal places).
S003-FR-017.a Per-file coverage **MUST** be read from each `<class filename="..." line-rate="...">` element when `line-rate` is present.
S003-FR-017.b The `line-rate` value **MUST** be converted from a decimal ratio to a percentage by multiplying by 100.
S003-FR-017.c If a Cobertura class omits `line-rate`, coverage **MUST** be computed from nested `<line hits="...">` elements as `(covered lines / total lines) * 100`.
S003-FR-017.d Cobertura paths **MUST** use the same absolute and project-relative key behavior as Clover paths.

S003-FR-018 [P1] The `CoverageReader` **MUST** provide project-level coverage for Cobertura XML by reading the root `<coverage line-rate="...">` attribute.
S003-FR-018.a If the root `line-rate` attribute is missing, global coverage **MUST** return `null`.
