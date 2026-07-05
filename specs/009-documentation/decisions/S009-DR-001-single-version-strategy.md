# S009 Decisions

## S009-DR-001: Single-Version Documentation Strategy

**Date**: 2026-04-24

### Decision

Parity docs use a single-version strategy: no URL-based version switching, no `/v1/` or `/v2/` paths, no version selector in the UI.

### Rationale

Parity ships frequently (as a Laravel Zero CLI tool distributed via Composer). Supporting multiple documentation versions would create maintenance overhead that outweighs the benefit. Users are expected to run the latest version, which is consistent with the Composer global install model.

When a breaking change requires significant documentation rework, the guide is replaced rather than versioned. The cost of a rewrite is lower than the ongoing cost of maintaining versioned copies.

### Consequences

- **All docs reflect `main`**: docs/testparity.com always serves the latest state of `parity-website/guide/`.
- **PR discipline required**: any PR that changes user-facing behavior must update the corresponding guide in the same PR.
- **No archived versions**: old guide content is not preserved on the website; it exists only in git history.

---

## S009-DR-002: Markdown Source as Canonical Content

**Date**: 2026-04-24

### Decision

`parity-website/guide/` is the canonical source of all website guide content. `parity-website/.vitepress/` is the VitePress configuration. The `parity-website/.vitepress/dist/` directory is a build artifact, not a source of truth.

### Rationale

Having markdown files as the canonical source enables:
- Markdown-first editing (no VitePress-specific syntax required)
- Easy content reuse (same files could be used for other renderers)
- Clear separation between content and presentation

The VitePress config in `parity-website/.vitepress/config.ts` renders the real Markdown files under `parity-website/guide/`.

### Consequences

- **Build step required**: `parity-website/` source must be built by VitePress before deployment.
- **Dist is derived**: the `dist/` directory is regenerated on every build and should not be edited manually.
- **Content lives in the website repo**: all new website guides are created in `parity-website/guide/`.

---

## S009-DR-003: Code Examples Must Be Verifiable

**Date**: 2026-04-24

### Decision

All code examples in documentation must be executable against the current `main` branch. Examples that cannot be verified automatically must include a comment noting the verification method.

### Rationale

Documentation that contains non-working code examples erodes user trust and creates support burden. Non-verified examples tend to diverge further from reality over time.

### Examples

Shell commands are verified by running them in the CI workflow. Configuration examples are verified by running `parity init` and comparing the output. PHP code examples are verified by reviewing against the actual `RuleInterface` contract and running the Parity test suite.

### Consequences

- **Examples are tested**: code examples that can be executed are run in CI or manually verified before merge.
- **Unverifiable examples are flagged**: if a code example cannot be automatically tested, a comment must explain the manual verification step.
- **Outdated examples block merge**: a PR that introduces an outdated code example is blocked from merge until corrected.
