# S009 Functional Requirements

### Guide Inventory and Coverage

S009-FR-001 [P1] The documentation **MUST** contain a guide for each of the 10 topics listed in the inventory table in `index.md`.
S009-FR-001.a The `getting-started.md` guide **MUST** cover: what Parity does, why it matters, quick start (install, init, check), and example output.
S009-FR-001.b The `installation.md` guide **MUST** cover: PHP/Composer requirements, global install, project dependency install, PATH configuration, verification steps, and update procedure.
S009-FR-001.c The `configuration.md` guide **MUST** cover: full `parity.yaml` schema (settings, coverage_xml, min_coverage, structure blocks), file mapping logic, multiple structure blocks, and CLI overrides.
S009-FR-001.d The `rules.md` guide **MUST** cover: all five built-in rules (test-exists, enforce-coverage-link, minimum-coverage, matched-coverage, coverage-attribution) with parameters, behavior, and example configurations.
S009-FR-001.e The `coverage.md` guide **MUST** cover: Clover XML format, PHPUnit XML format, format comparison table, generating coverage with Pest and PHPUnit, and which rules require which format.
S009-FR-001.f The `pest-support.md` guide **MUST** cover: chained `->covers()`, file-level `covers()`, multiple classes, Pest configuration via `pest-covers` linker, and mixed project configuration.
S009-FR-001.g The `phpunit-support.md` guide **MUST** cover: `#[CoversClass]` attribute, string literals, multiple attributes, PHPUnit configuration via `php-attribute` linker, legacy `@covers` annotation rejection, and mixed project configuration.
S009-FR-001.h The `ci-integration.md` guide **MUST** cover: exit codes, JSON output format, GitHub Actions workflow, GitLab CI pipeline, Bitbucket Pipelines pipeline, and general CI tips.
S009-FR-001.i The `plugins.md` guide **MUST** cover: plugin locations (project-local, global user, Composer package), RuleInterface contract, writing a custom rule, using parameters, distributing via Composer, and informational rules.

### VitePress Configuration

S009-FR-002 [P1] The `parity-website/.vitepress/config.ts` file **MUST** define: `title` as "Parity", `description` as "Structural test parity for PHP projects", `cleanUrls: true`, `ignoreDeadLinks: true`, and a favicon link in the `head` array.
S009-FR-002.a The VitePress source root **MUST** be the `parity-website/` repository, with guides stored as real Markdown files in `parity-website/guide/`.
S009-FR-002.b The VitePress config **MUST** configure the Vite server `fs.allow` to include the website root so local docs render in dev mode.
S009-FR-002.c The VitePress config **MAY** preserve symlinks for local development compatibility, but published guide files **MUST NOT** rely on symlinks.

### Navigation Structure

S009-FR-003 [P1] The VitePress sidebar **MUST** organize guides into four groups: Introduction (Getting Started, Installation), Core Concepts (Configuration, Rules, Coverage), Integrations (Pest Support, PHPUnit Support, CI Integration), and Advanced (Plugins).
S009-FR-003.a The sidebar **MUST** include a link for every guide in the inventory.
S009-FR-003.b The navigation **MUST** include a "Guide" link pointing to `/guide/getting-started` and a "GitHub" link pointing to the Parity repository.
S009-FR-003.c The social links section **MUST** include a GitHub icon linking to the Parity repository.

### Writing Style and Tone

S009-FR-004 [P1] All guides **MUST** use a tone that is: direct, practical, authoritative without being arrogant, and oriented toward developers who ship code.
S009-FR-004.a Each guide **MUST** start with a frontmatter `title` and `description` that accurately reflect the guide content.
S009-FR-004.b The first heading in each guide **MUST** be an H1 (`#`) matching the `title` from frontmatter.
S009-FR-004.c Guides **MUST** use sentence case for headings (e.g., "Built-in Rules", not "Built-In Rules" or "Built-in rules").
S009-FR-004.d Code blocks **MUST** always specify a language for syntax highlighting (e.g., ` ```bash `, ` ```php `, ` ```yaml `).
S009-FR-004.e Internal guide-to-guide links **MUST** use relative VitePress-compatible paths (e.g., `./configuration` not `../guide/configuration.md`).
S009-FR-004.f Tables **MUST** be used for reference content (configuration keys, rule parameters, format comparisons); prose **MUST** be used for conceptual explanations.
S009-FR-004.g Admonitions (tip, warning, info) **MUST** be used sparingly and only for content that genuinely warrants extra attention (installation PATH gotchas, breaking changes, caveats).

### Code Example Requirements

S009-FR-005 [P1] All code examples in documentation **MUST** be syntactically correct for their language and **MUST** work against the current `main` branch of the Parity repository.
S009-FR-005.a Code examples **MUST** use the correct Parity command invocation (`parity check`, `parity init`) as documented in S001.
S009-FR-005.b Configuration examples **MUST** use the `parity.yaml` schema as defined in S006 and validated by S002 rules.
S009-FR-005.c Code examples for plugin authoring **MUST** implement `RuleInterface` as specified in S005.
S009-FR-005.d Any code example that references a class name, method name, or configuration key **MUST** match the actual implementation.
S009-FR-005.e Code examples **MUST** include comments only when they explain intent or clarify non-obvious behavior; extraneous comments that restate obvious code are not allowed.
S009-FR-005.f Shell commands for installation **MUST** use the correct `composer global require testparity/parity` syntax as shown in the existing `installation.md` guide.

### Frontmatter Schema

S009-FR-006 [P1] Every guide file **MUST** begin with a YAML frontmatter block containing exactly two fields: `title` (string) and `description` (string).
S009-FR-006.a The `title` field **MUST** be the guide title as it appears in the H1 heading and in the sidebar navigation.
S009-FR-006.b The `description` field **MUST** be a single sentence that describes what the guide covers, used for SEO and metadata purposes.
S009-FR-006.c The frontmatter **MUST** use three dashes (`---`) as opening and closing delimiters.

### Authoring Workflow

S009-FR-007 [P1] To add a new guide, an author **MUST** create a `.md` file in `parity-website/guide/` with valid frontmatter, correct content per the guide type requirements, and valid internal links.
S009-FR-007.a The author **MUST** update the VitePress sidebar in `parity-website/.vitepress/config.ts` to include the new guide in the appropriate section.
S009-FR-007.b The author **MUST** run the VitePress dev server to verify the guide renders correctly and all links resolve before submitting.
S009-FR-007.c To edit an existing guide, the author **MUST** edit the corresponding `.md` file in `parity-website/guide/` and re-verify rendering.
S009-FR-007.d All guide files **MUST** use UTF-8 encoding.

### Versioning Strategy

S009-FR-008 [P1] The documentation system **MUST** maintain a single version (no version selector, no versioned subdirectories).
S009-FR-008.a All documentation on the deployed website **MUST** reflect the current state of the `main` branch.
S009-FR-008.b When a new feature is merged that changes behavior documented in an existing guide, that guide **MUST** be updated in the same PR as the feature.
S009-FR-008.c No guide **MUST** be published that documents behavior that does not exist in `main`.

### Docs-Implementation Synchronization

S009-FR-009 [P1] Every functional requirement in S001 through S008 that is user-facing (CLI flags, config keys, rule names, plugin API) **MUST** be documented in at least one guide.
S009-FR-009.a When a PR changes a user-facing behavior (adds a flag, changes a config key, adds a rule parameter), the PR **MUST** include a documentation update if a guide covers the affected behavior.
S009-FR-009.b If a guide and implementation disagree, the implementation takes precedence for behavior and the guide **MUST** be updated to match.
S009-FR-009.c The website repository **MUST** keep generated build output, local agent files, and analysis artifacts out of Git via `.gitignore`.

### Single-Version Constraint

S009-FR-010 [P2] The documentation **SHOULD NOT** support URL-based version switching (no `/v1/`, `/v2/` paths).
S009-FR-010.a If a breaking change requires significant documentation rework, the guide **SHOULD** be replaced rather than versioned.

### Search Integration

S009-FR-011 [P2] The VitePress config **SHOULD** be extended to include Algolia DocSearch or an equivalent search integration for full-text search of all guides.

### Dark Mode Support

S009-FR-012 [P2] The VitePress theme **SHOULD** support dark mode by inheriting from the default VitePress theme without custom overrides beyond branding colors (green brand palette as per existing `custom.css`).
