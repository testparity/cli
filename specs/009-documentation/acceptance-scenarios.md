# S009 Acceptance Scenarios

### S009-AS-001 Guide Renders in VitePress [P1]

**Given** a new guide file has been created in `parity-website/guide/` with valid frontmatter and correct content
**When** the VitePress dev server is started from `parity-website/`
**Then** the guide appears in the sidebar under the correct section and is accessible at the expected URL
**And** all internal links within the guide resolve to other existing guides

### S009-AS-002 Code Examples Execute Successfully [P1]

**Given** a guide contains code examples (shell commands, PHP code, YAML configuration)
**When** a developer copies and pastes the example into a terminal or editor
**Then** the example executes without modification against the current `main` branch
**And** no syntax errors or runtime errors occur due to outdated API references

### S009-AS-003 Navigation Links Are Valid [P1]

**Given** a guide references another guide via an internal link
**When** the VitePress build runs with `ignoreDeadLinks: true`
**Then** no dead links are reported in the build output for internal guide references
**And** clicking the link navigates to the correct guide page

### S009-AS-004 Configuration Reference Stays in Sync [P1]

**Given** the `parity.yaml` schema changes (a new config key is added or an existing key's behavior changes)
**When** a PR is merged that changes the schema
**Then** the `configuration.md` guide is updated in the same PR to reflect the new schema
**And** any example configurations in other guides that use the changed key are also updated

### S009-AS-005 Plugin Authoring Guide Matches RuleInterface [P1]

**Given** the `plugins.md` guide describes how to implement `RuleInterface`
**When** a developer follows the guide to write a custom rule
**Then** the rule loads successfully in Parity without requiring workarounds or corrections to the guide
**And** all method signatures in the example match the actual `RuleInterface` contract defined in S005

### S009-AS-006 CI Integration Examples Work in GitHub Actions [P1]

**Given** a developer copies the GitHub Actions workflow from `ci-integration.md`
**When** the workflow runs on a GitHub Actions runner with PHP 8.2 and Parity installed
**Then** `parity check` executes and produces the expected table or JSON output
**And** the exit code correctly reflects whether violations were found

### S009-AS-007 Pest and PHPUnit Guides Reflect Actual Detection Logic [P1]

**Given** a developer reads `pest-support.md` and `phpunit-support.md`
**When** they configure `enforce-coverage-link` with the documented linkers
**Then** Parity detects the coverage declarations exactly as described in the guides
**And** no false positives or false negatives occur for the documented syntax patterns

### S009-AS-008 Coverage Format Guide Is Accurate [P1]

**Given** a developer reads `coverage.md` to understand Clover XML vs PHPUnit XML
**When** they configure coverage generation using the documented commands
**Then** the resulting coverage data is readable by the coverage rules as documented
**And** the format comparison table correctly identifies which features are available per format

### S009-AS-009 Getting Started Guide Installs and Runs Successfully [P1]

**Given** a developer with PHP 8.2 and Composer installed follows the getting-started guide from a fresh project
**When** they run the three quick-start commands: `composer global require testparity/parity`, `parity init`, `parity check`
**Then** `parity init` creates a `parity.yaml` file and `parity check` produces output without errors
**And** the example output shown in the guide matches the actual output format
