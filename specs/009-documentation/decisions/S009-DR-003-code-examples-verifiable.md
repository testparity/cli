# S009-DR-003: Code Examples Must Be Verifiable

**Date**: 2026-04-24

### Decision

All code examples in documentation must be executable against the current `main` branch. Examples that cannot be verified automatically must include a comment noting the verification method.

### Rationale

Documentation that contains non-working code examples erodes user trust and creates support burden. Non-verified examples tend to diverge further from reality over time.

### Verification Methods

- **Shell commands**: verified by running them in CI or during manual QA
- **Configuration examples**: verified by running `parity init` and comparing output
- **PHP code examples**: verified by reviewing against the actual `RuleInterface` contract and running the Parity test suite

### Consequences

- **Examples are tested**: code examples that can be executed are run in CI or manually verified before merge
- **Unverifiable examples are flagged**: if a code example cannot be automatically tested, a comment must explain the manual verification step
- **Outdated examples block merge**: a PR that introduces an outdated code example is blocked from merge until corrected