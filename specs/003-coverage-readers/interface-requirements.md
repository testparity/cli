# S003 Interface Requirements

### CoverageReader (Clover XML)

S003-IF-001 [P1] `CoverageReader::read()` **MUST** conform to the following signature and return type:

```php
/**
 * @param string $coverageXmlPath  Absolute path to the Clover XML file.
 * @param string|null $projectRoot  Absolute path to the project root (enables relative-path keys).
 * @return array<string, float>     Map of file path => coverage percentage (0.0--100.0).
 */
public function read(string $coverageXmlPath, ?string $projectRoot = null): array
```

S003-IF-001.a The returned map **MUST** contain normalized absolute path keys for every `<file>` element.
S003-IF-001.b When `$projectRoot` is non-null, the returned map **MUST** additionally contain relative path keys (forward-slashed, no leading slash).
S003-IF-001.c Coverage values **MUST** be floats in the range `[0.0, 100.0]`, rounded to 2 decimal places.

S003-IF-002 [P1] `CoverageReader::readGlobalCoverage()` **MUST** conform to the following signature:

```php
/**
 * @param string $coverageXmlPath  Absolute path to the Clover XML file.
 * @return float|null              Global coverage percentage (0.0--100.0), or null if unavailable.
 */
public function readGlobalCoverage(string $coverageXmlPath): ?float
```

### PhpUnitXmlCoverageReader (PHPUnit XML)

S003-IF-003 [P1] `PhpUnitXmlCoverageReader::read()` **MUST** conform to the following signature and return type:

```php
/**
 * @param string $dirPath          Absolute path to the PHPUnit XML coverage directory.
 * @param string|null $projectRoot  Absolute path to the project root (enables absolute-path keys).
 * @return array{
 *     coverage: array<string, float>,
 *     testsByFile: array<string, list<string>>,
 *     lineCoverage: array<string, array<int, list<string>>>,
 *     totalExecutable: array<string, int>,
 *     globalPercent: float|null
 * }
 */
public function read(string $dirPath, ?string $projectRoot = null): array
```

S003-IF-003.a `coverage` **MUST** map file paths to coverage percentages (0.0--100.0).
S003-IF-003.b `testsByFile` **MUST** map file paths to deduplicated lists of fully-qualified test method names (e.g., `Tests\Unit\FooTest::test_bar`).
S003-IF-003.c `lineCoverage` **MUST** map file paths to `array<int, list<string>>` where keys are 1-based line numbers and values are test method names covering that line.
S003-IF-003.d `totalExecutable` **MUST** map file paths to the total count of executable lines in that file (integer >= 0).
S003-IF-003.e `globalPercent` **MUST** be a float (0.0--100.0) when available from `index.xml`, or `null` when the root directory totals are missing.

### Clover XML Schema Contract

S003-IF-004 [P1] The Clover XML reader **MUST** handle documents conforming to the following minimal structure:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="TIMESTAMP">
  <project timestamp="TIMESTAMP">
    <metrics files="N" loc="N" ncloc="N" classes="N"
             methods="N" coveredmethods="N"
             statements="N" coveredstatements="N"
             elements="N" coveredelements="N"/>
    <package name="PACKAGE">
      <file name="/absolute/path/to/File.php">
        <line num="N" type="stmt" count="N"/>
        <!-- ... -->
        <metrics loc="N" ncloc="N" classes="N"
                 methods="N" coveredmethods="N"
                 statements="N" coveredstatements="N"
                 elements="N" coveredelements="N"/>
      </file>
    </package>
  </project>
</coverage>
```

S003-IF-004.a The reader **MUST** select `<file>` elements via XPath `//file[@name]`, regardless of nesting depth.
S003-IF-004.b The reader **MUST** select the project-level `<metrics>` element via `//metrics[@files]`.

### PHPUnit XML Schema Contract

S003-IF-005 [P1] The PHPUnit XML reader **MUST** handle the directory structure produced by `--coverage-xml`:

```
coverage-xml/
  index.xml          # Directory listing with per-file hrefs and project totals
  File.php.xml       # Per-file coverage with line-level test attribution
  Sub/
    Nested.php.xml   # Nested subdirectory files
```

S003-IF-005.a `index.xml` **MUST** use namespace `https://schema.phpunit.de/coverage/1.0`.
S003-IF-005.b The reader **MUST** locate file entries via `//p:file[@href]` (or local-name fallback).
S003-IF-005.c Per-file XML documents **MUST** contain a `<file path="..." name="...">` element under the coverage namespace.
S003-IF-005.d Per-line test attribution **MUST** be read from `<coverage><line nr="N"><covered by="TestClass::method"/></line></coverage>` elements.
S003-IF-005.e Per-file totals **MUST** be read from `<totals><lines percent="N" executable="N" .../></totals>`.

### Normalized Output for CheckCommand

S003-IF-006 [P1] The `loadCoverageData()` method in `CheckCommand` **MUST** return a unified associative array with the following keys:

```php
[
    'coverageMap'     => array<string, float>,    // file path => coverage %
    'testsByFile'     => array<string, list<string>>,  // file path => test names
    'lineCoverage'    => array<string, array<int, list<string>>>,  // file path => line => tests
    'totalExecutable' => array<string, int>,       // file path => executable line count
    'globalPercent'   => float|null,               // project-level coverage %
    'hasAttributionCoverage' => bool,              // true if the selected format exposes per-test line attribution
]
```

S003-IF-006.a When Clover XML is used, `testsByFile`, `lineCoverage`, and `totalExecutable` **MUST** be empty arrays.
S003-IF-006.b When Clover XML or Cobertura XML are used, `hasAttributionCoverage` **MUST** be `false`.
S003-IF-006.c When Parity per-test reports, Parity JSON, or PHPUnit XML are used, `hasAttributionCoverage` **MUST** be `true`.
S003-IF-006.d `globalPercent` **MUST** be `null` when not available or when `min_coverage_global` is not configured (Clover only reads global coverage when `min_coverage_global` is set).
