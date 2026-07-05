# S007 Acceptance Scenarios

### S007-AS-001 Standard Laravel source-to-FQCN conversion [P1]

**Given** a `NamespaceHelper` constructed with `namespace_roots: { 'app' => 'App', 'tests' => 'Tests' }`
**When** `pathToFqcn('app/Services/Auth/LoginService.php')` is called
**Then** the result **MUST** be `App\Services\Auth\LoginService`

**And given** the same helper
**When** `pathToFqcn('app/Models/User.php')` is called
**Then** the result **MUST** be `App\Models\User`

**And given** the same helper
**When** `pathToFqcn('app/Http/Controllers/Api/V2/UserController.php')` is called
**Then** the result **MUST** be `App\Http\Controllers\Api\V2\UserController`

### S007-AS-002 Standard test path derivation [P1]

**Given** a `NamespaceHelper` with default settings
**When** `sourcePathToTestPath('app/Services/AuthService.php', 'app/Services', 'tests/Unit/Services')` is called
**Then** the result **MUST** be `tests/Unit/Services/AuthServiceTest.php`

**And given** the same helper
**When** `sourcePathToTestPath('app/Services/Auth/LoginService.php', 'app/Services', 'tests/Unit/Services')` is called
**Then** the result **MUST** be `tests/Unit/Services/Auth/LoginServiceTest.php`

### S007-AS-003 file_map override applied [P1]

**Given** a structure block with `paths.source: app/Services`, `paths.test: tests/Unit/Services`, and `file_map: { "Legacy/OldHelper.php": "Helpers/OldHelperTest.php" }`
**When** Parity processes the source file `app/Services/Legacy/OldHelper.php`
**Then** the expected test path **MUST** be `tests/Unit/Services/Helpers/OldHelperTest.php`
**And** the algorithmic derivation (`tests/Unit/Services/Legacy/OldHelperTest.php`) **MUST NOT** be used

### S007-AS-004 Multiple structure blocks each resolve independently [P1]

**Given** a `parity.yaml` with two structure blocks:
```yaml
structure:
  - name: "Unit Services"
    paths:
      source: "app/Services"
      test: "tests/Unit/Services"
  - name: "Feature Controllers"
    paths:
      source: "app/Http/Controllers"
      test: "tests/Feature/Controllers"
```
**When** Parity processes source file `app/Services/AuthService.php`
**Then** the expected test path **MUST** be `tests/Unit/Services/AuthServiceTest.php` (from the first block)

**And when** Parity processes source file `app/Http/Controllers/UserController.php`
**Then** the expected test path **MUST** be `tests/Feature/Controllers/UserControllerTest.php` (from the second block)

### S007-AS-005 Deeply nested path conversion [P1]

**Given** a `NamespaceHelper` with default roots and a deeply nested source path
**When** `pathToFqcn('app/Domain/Billing/Stripe/Webhooks/InvoicePaid.php')` is called
**Then** the result **MUST** be `App\Domain\Billing\Stripe\Webhooks\InvoicePaid`

**And given** the same helper
**When** `sourcePathToTestPath('app/Domain/Billing/Stripe/Webhooks/InvoicePaid.php', 'app', 'tests/Unit')` is called
**Then** the result **MUST** be `tests/Unit/Domain/Billing/Stripe/Webhooks/InvoicePaidTest.php`

### S007-AS-006 Test directory FQCN resolution [P1]

**Given** a `NamespaceHelper` with default roots
**When** `pathToFqcn('tests/Unit/Services/AuthServiceTest.php')` is called
**Then** the result **MUST** be `Tests\Unit\Services\AuthServiceTest`

**And given** the same helper
**When** `pathToFqcn('tests/Feature/Controllers/UserControllerTest.php')` is called
**Then** the result **MUST** be `Tests\Feature\Controllers\UserControllerTest`

### S007-AS-007 Custom namespace separator [P2]

**Given** a `NamespaceHelper` constructed with `namespace_separator: '.'` and `namespace_roots: { 'src' => 'com.example' }`
**When** `pathToFqcn('src/services/AuthService.java')` is called with `source_extension: '.java'`
**Then** the result **MUST** be `com.example.services.AuthService`

### S007-AS-008 End-to-end check command uses mapping [P1]

**Given** a project with `parity.yaml` containing:
```yaml
settings:
  namespace_roots:
    app: App
    tests: Tests
structure:
  - name: "Services"
    paths:
      source: "app/Services"
      test: "tests/Unit/Services"
    rules:
      - test-exists
      - enforce-coverage-link
```
**And** a source file at `app/Services/PaymentService.php` with class `App\Services\PaymentService`
**And** a test file at `tests/Unit/Services/PaymentServiceTest.php` with `covers(PaymentService::class)` and `use App\Services\PaymentService`
**When** `parity check` is executed
**Then** the derived test path **MUST** be `tests/Unit/Services/PaymentServiceTest.php`
**And** the expected source FQCN passed to the coverage linker **MUST** be `App\Services\PaymentService`
**And** the coverage link validation **MUST** pass
