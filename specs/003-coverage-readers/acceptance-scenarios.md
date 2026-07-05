# S003 Acceptance Scenarios

### S003-AS-001 Read Clover XML happy path [P1]

**Given** a valid Clover XML file at `clover.xml` containing two `<file>` elements:
  - `File A`: 10 statements, 8 covered
  - `File B`: 5 statements, 5 covered
**When** `CoverageReader::read('clover.xml', '/project')` is called
**Then** the returned map contains `File A => 80.0` and `File B => 100.0`
**And** both absolute-path keys and relative-path keys are present

### S003-AS-002 Read PHPUnit XML happy path [P1]

**Given** a valid PHPUnit XML directory at `coverage-xml/` with:
  - `index.xml` referencing two per-file XMLs
  - `UserService.php.xml` with 90% coverage and 3 covering tests
  - `OrderService.php.xml` with 60% coverage and 1 covering test
**When** `PhpUnitXmlCoverageReader::read('coverage-xml/', '/project')` is called
**Then** `coverage` contains both files with their respective percentages
**And** `testsByFile` maps each file to its list of covering test identifiers
**And** `lineCoverage` maps each file to its line-level test attribution
**And** `totalExecutable` maps each file to its executable line count

### S003-AS-003 Format auto-detection selects correct reader [P1]

**Given** `coverage_xml` is configured as `['coverage-xml', 'clover.xml', 'cobertura.xml']`
**And** `coverage-xml/` is a directory containing `index.xml`
**When** `loadCoverageData()` is called
**Then** the PHPUnit XML reader is used (`isPhpUnitXml` is `true`)
**And** per-test attribution data is populated

### S003-AS-004 Multi-path fallback selects first existing [P1]

**Given** `coverage_xml` is configured as `['coverage-xml', 'clover.xml', 'cobertura.xml']`
**And** `coverage-xml/` does not exist
**And** `clover.xml` exists as a valid Clover file
**When** `loadCoverageData()` is called
**Then** the Clover reader is used (`isPhpUnitXml` is `false`)
**And** `testsByFile` is an empty array

### S003-AS-005 Per-test attribution populates testsByFile [P1]

**Given** a PHPUnit XML per-file document where line 10 is covered by `Tests\Unit\FooTest::test_create` and `Tests\Unit\BarTest::test_index`, and line 15 is covered by `Tests\Unit\FooTest::test_create`
**When** the reader processes this file
**Then** `testsByFile` for this file contains exactly `['Tests\Unit\FooTest::test_create', 'Tests\Unit\BarTest::test_index']` (deduplicated)
**And** `lineCoverage` for line 10 contains both test identifiers
**And** `lineCoverage` for line 15 contains only `Tests\Unit\FooTest::test_create`

### S003-AS-006 Source prefix prepended to relative paths [P2]

**Given** `index.xml` contains `<project source="/project/app">`
**And** a per-file XML has `<file path="Models" name="User.php">`
**When** the reader processes this file
**Then** the relative path key is `app/Models/User.php` (source basename `app` prepended)

### S003-AS-007 Missing coverage file returns empty/null [P1]

**Given** the coverage file path points to a non-existent file
**When** `CoverageReader::read()` is called
**Then** the returned map is an empty array
**And** no exception is thrown

**Given** the coverage file path points to a non-existent file
**When** `CoverageReader::readGlobalCoverage()` is called
**Then** `null` is returned
**And** no exception is thrown

### S003-AS-008 Malformed XML returns empty/null [P1]

**Given** the coverage file contains `<not-valid>xml<<<`
**When** `CoverageReader::read()` is called
**Then** the returned map is an empty array
**And** no PHP warnings are emitted

**Given** a PHPUnit XML `index.xml` contains malformed XML
**When** `PhpUnitXmlCoverageReader::read()` is called
**Then** all returned maps are empty and `globalPercent` is `null`

### S003-AS-009 Global coverage from Clover project metrics [P1]

**Given** a valid Clover XML file with project-level `<metrics files="5" statements="100" coveredstatements="85">`
**When** `CoverageReader::readGlobalCoverage()` is called
**Then** `85.0` is returned

### S003-AS-010 Global coverage from PHPUnit XML root directory [P1]

**Given** `index.xml` contains `<directory name="/"><totals><lines percent="72.5" .../></totals></directory>`
**When** `PhpUnitXmlCoverageReader::read()` is called
**Then** `globalPercent` is `72.5`

### S003-AS-011 Read Cobertura XML happy path [P1]

**Given** a valid Cobertura XML file at `cobertura.xml` containing a `<class filename="src/Foo.ts" line-rate="0.75">`
**And** the root `<coverage>` element has `line-rate="0.80"`
**When** `CoverageReader::read('cobertura.xml', '/project')` is called
**Then** the returned map contains `src/Foo.ts => 75.0`
**And** `CoverageReader::readGlobalCoverage('cobertura.xml')` returns `80.0`
