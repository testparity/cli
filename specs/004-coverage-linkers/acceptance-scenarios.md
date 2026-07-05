# S004 Acceptance Scenarios

### S004-AS-001 Pest chained covers() detected [P1]

**Given** a Pest test file containing `it('works', function () {})->covers(FooService::class);` and a use statement `use App\Services\FooService;`
**When** the `PestCoversLinker` extracts covered classes with the corresponding use map
**Then** the result **MUST** contain exactly `['App\Services\FooService']`

### S004-AS-002 Pest file-level covers() detected [P1]

**Given** a Pest test file containing `covers(FooService::class);` at the top level (not chained) and a use statement `use App\Services\FooService;`
**When** the `PestCoversLinker` extracts covered classes with the corresponding use map
**Then** the result **MUST** contain exactly `['App\Services\FooService']`

### S004-AS-003 PHPUnit CoversClass attribute detected [P1]

**Given** a PHPUnit test file containing:
```php
use PHPUnit\Framework\Attributes\CoversClass;
use App\Services\FooService;

#[CoversClass(FooService::class)]
class FooServiceTest extends TestCase {}
```
**When** the `PhpAttributeLinker` extracts covered classes with the corresponding use map
**Then** the result **MUST** contain exactly `['App\Services\FooService']`

### S004-AS-004 Auto mode selects correct linker per file type [P1]

**Given** a `CoverageLinkerRegistry` created with `fromConfig(['auto'])`
**When** presented with a Pest test file (no class declaration, has `it()` calls)
**Then** the registry **MUST** return `linker: 'pest-covers'` with the correct covered classes

**And given** the same registry
**When** presented with a PHPUnit test file (has a class declaration and `#[CoversClass()]`)
**Then** the registry **MUST** return `linker: 'php-attribute'` with the correct covered classes

### S004-AS-005 Multiple classes extracted from single file [P1]

**Given** a Pest test file containing `->covers(FooService::class, BarService::class)` with corresponding use statements
**When** the `PestCoversLinker` extracts covered classes
**Then** the result **MUST** contain exactly `['App\Services\FooService', 'App\Services\BarService']` in declaration order

**And given** a PHPUnit test file containing two separate `#[CoversClass()]` attributes
**When** the `PhpAttributeLinker` extracts covered classes
**Then** the result **MUST** contain both classes in declaration order

### S004-AS-006 Custom attribute FQCN works [P2]

**Given** a `PhpAttributeLinker` constructed with `'My\Custom\Covers'`
**And** a test file containing `#[Covers(FooService::class)]` with the corresponding use map
**When** the linker extracts covered classes
**Then** the result **MUST** contain the resolved FQCN of `FooService`

### S004-AS-007 Validation rejects wrong FQCN in coverage declaration [P1]

**Given** a test file that declares `->covers(BarService::class)` but the expected source class is `App\Services\FooService`
**When** `ParityChecker::validateCoverageLink()` validates the coverage link
**Then** the result **MUST** have `valid: false` and `error` **MUST** include both the found class and the expected class

### S004-AS-008 No linker matches returns empty result [P1]

**Given** a `CoverageLinkerRegistry` with both default linkers
**And** a file that is neither a Pest file nor contains a class declaration (e.g. a plain PHP script)
**When** the registry's `extractCoveredClasses()` is called
**Then** the result **MUST** be `['linker' => null, 'classes' => []]`

**And** `hasSupport()` **MUST** return `false` for the same file content
