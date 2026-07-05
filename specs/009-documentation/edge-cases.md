# S009 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S009-EC-001 | A new feature is merged but the guide that should cover it has not been updated | The PR reviewer **MUST** block the merge until the relevant guide is updated or a new guide is added |
| S009-EC-002 | An internal link in a guide points to a guide that was moved or renamed | The link **MUST** be updated to the correct path before the moving PR is merged; broken links are not acceptable |
| S009-EC-003 | A code example in a guide references a class or method that was refactored in the same PR | The code example **MUST** be updated to reflect the new implementation before the PR is merged |
| S009-EC-004 | A guide documents an experimental feature that has not yet been merged to `main` | The guide **MUST NOT** be merged until the feature is merged; experimental docs belong in the feature branch |
| S009-EC-005 | A guide uses a configuration key that no longer exists in the current schema | The VitePress build **MUST** warn about dead configuration references in docs; the key must be corrected or removed |
| S009-EC-006 | The VitePress sidebar is updated but the corresponding guide file does not exist | The sidebar entry **MUST** be removed or the guide file created before the website build succeeds |
| S009-EC-007 | A guide references an external URL that has become unavailable | External URLs **SHOULD** be checked periodically; broken external links **SHOULD** be reported and fixed |
| S009-EC-008 | A guide is created for a rule or config key that does not yet exist (future feature) | Docs for unreleased features **MUST NOT** be merged to `main`; they belong in a feature branch until the feature ships |