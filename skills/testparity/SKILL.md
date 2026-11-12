---
name: testparity
description: Use TestParity to review associated-test coverage for changed executable lines in a Git branch or pull request. Use when fixing a parity pr failure or adding behavior-focused tests for a change.
---

# Review changed-line coverage with TestParity

1. Identify the PR target ref. Run from the project directory containing `parity.yaml`. Ensure the target ref and enough Git history to compute a merge base are available locally. Stage new source files so Git includes them in the report.
2. Read the configured source and test structures, any `file_map` overrides, and the local test commands. Run the repository's regular test checks and generate fresh attribution with `parity test --no-check`. If the command fails, address the reported test or coverage setup problem before interpreting results.
3. Run `parity pr --base=<ref> --format=json` and save the baseline. Exit 0 indicates no gate failures. Exit 1 indicates a configured threshold failed or an associated test is missing for changed executable lines. Exit 2 indicates an input error or unavailable attribution. If attribution remains unavailable after checking the ref and regenerating coverage, stop and report the affected files and the missing evidence.
4. For each actionable file, read the diff and open its `associated_test`. Inspect `unrelated_lines` and `uncovered_lines`. These classifications describe collected reports from tests mapped to configured source files. Use the configured mapping if present. If the expected test file is missing, confirm the convention or mapping before creating it. Add the smallest behavior-focused test that verifies the changed result or side effect. Preserve existing assertions, thresholds and coverage configuration. Keep production code edits tied to the requested behavior.
5. Rerun the affected tests and regenerate fresh coverage with `parity test --no-check`. Rerun the same PR command and the project's existing test and coverage gates. Summarize the test changes, associated coverage before and after, gate results, and any remaining gaps.
