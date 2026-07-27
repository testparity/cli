# S011: Parity Test Execution & Per-Test Coverage Reports

| Field | Value |
|-------|-------|
| Spec | S011 |
| Feature | Parity Test Execution & Per-Test Coverage Reports |
| Date | 2026-07-08 |
| Status | Implemented |
| Author | Codex |

## Overview

Parity has two coverage ingestion modes:

- Read an existing coverage artifact with `parity check`.
- Generate attribution-grade coverage artifacts with `parity test`.

This specification defines the public contract for `parity test` and for Parity's directory-based per-test coverage report format. The command exists because many test runners can emit aggregate coverage, but only some can emit line-level attribution showing which specific test covered which specific source line. `parity test` closes that gap by executing each expected test file individually, normalizing the resulting single-test coverage artifact, and producing a Parity-native per-test report set that `parity check` can consume deterministically.

The spec covers four concerns: configuration under the `test:` block in `parity.yaml`, CLI behavior of `parity test`, the on-disk JSON report directory format, and the default follow-up invocation of `parity check`. It is the normative source for the command contract (S011-FR), interface and schema requirements (S011-IF), acceptance scenarios (S011-AS), edge-case handling (S011-EC), and success criteria (S011-SC).

## User Scenarios

S011-US-001 [P1] As a developer using a coverage format without per-test attribution, I want Parity to run each expected test file individually so that matched coverage can still be computed correctly.

S011-US-002 [P1] As a CI pipeline, I want a deterministic on-disk artifact format for one-report-per-test coverage so that `parity check` can consume it without custom adapters.

S011-US-003 [P1] As a maintainer, I want `parity test` to run `parity check` by default after generating reports so that the command is useful as a release gate by itself.

S011-US-004 [P2] As a developer, I want placeholder-based command and coverage paths so that I can integrate Parity with different runners and languages.

S011-US-005 [P2] As a contributor, I want the per-test report directory to be simple JSON so that fixtures, debugging, and CI artifacts remain inspectable without proprietary tooling.

S011-US-006 [P1] As a developer, I want failed or timed-out generation to preserve my last complete reports and never delete unrelated files when a path is misconfigured.

## Requirements Summary

| ID | Type | Priority | Title | Status |
|----|------|----------|-------|--------|
| S011-FR-001 | Functional | P1 | `parity test` command availability | Implemented |
| S011-FR-002 | Functional | P1 | Required `test` configuration | Implemented |
| S011-FR-003 | Functional | P1 | Expected-test discovery | Implemented |
| S011-FR-004 | Functional | P1 | Per-test command execution | Implemented |
| S011-FR-005 | Functional | P1 | Coverage artifact normalization | Implemented |
| S011-FR-006 | Functional | P1 | Per-test report directory creation | Implemented |
| S011-FR-007 | Functional | P1 | Manifest generation | Implemented |
| S011-FR-008 | Functional | P1 | Automatic `parity check` handoff | Implemented |
| S011-FR-009 | Functional | P2 | `--no-check` behavior | Implemented |
| S011-FR-010 | Functional | P2 | Output directory override | Implemented |
| S011-FR-011 | Functional | P2 | Placeholder expansion | Implemented |
| S011-FR-012 | Functional | P1 | Missing required config errors | Implemented |
| S011-FR-013 | Functional | P1 | Failed test process handling | Implemented |
| S011-FR-014 | Functional | P2 | No discovered tests behavior | Implemented |
| S011-FR-015 | Functional | P1 | `parity check` compatibility of generated reports | Implemented |
| S011-FR-016 | Functional | P1 | Native report schema and path validation | Implemented |
| S011-FR-017 | Functional | P1 | Coverage artifact path safety | Implemented |
| S011-IF-001 | Interface | P1 | `parity test` CLI signature | Implemented |
| S011-IF-002 | Interface | P1 | `test` config block schema | Implemented |
| S011-IF-003 | Interface | P1 | Placeholder contract | Implemented |
| S011-IF-004 | Interface | P1 | Per-test report manifest schema | Implemented |
| S011-IF-005 | Interface | P1 | Per-test report file schema | Implemented |
| S011-IF-006 | Interface | P1 | Normalizer input formats | Implemented |
| S011-AS-001 | Acceptance | P1 | Generate one report per expected test | Implemented |
| S011-AS-002 | Acceptance | P1 | Run `parity check` by default after generation | Implemented |
| S011-AS-003 | Acceptance | P1 | `--no-check` skips follow-up analysis | Implemented |
| S011-AS-004 | Acceptance | P1 | Missing `test.command` fails fast | Implemented |
| S011-AS-005 | Acceptance | P1 | Missing `test.coverage` fails fast | Implemented |
| S011-AS-006 | Acceptance | P2 | Output override changes report directory | Implemented |
| S011-AS-007 | Acceptance | P2 | Non-attribution runner artifact is normalized | Implemented |
| S011-AS-008 | Acceptance | P2 | Explicitly mapped tests participate in discovery | Implemented |
| S011-AS-009 | Acceptance | P1 | Missing coverage preserves prior reports | Implemented |
| S011-AS-010 | Acceptance | P1 | Dangerous output target is rejected | Implemented |
| S011-AS-011 | Acceptance | P1 | Runner timeout fails cleanly | Implemented |
| S011-AS-012 | Acceptance | P1 | Nested symlink and overlapping artifact targets are rejected | Implemented |
| S011-EC-001 | Edge Case | P1 | Config file path cannot be resolved | Implemented |
| S011-EC-002 | Edge Case | P1 | Discovered test command exits non-zero | Implemented |
| S011-EC-003 | Edge Case | P1 | Coverage artifact path does not exist after successful test run | Implemented |
| S011-EC-004 | Edge Case | P2 | Source structure path does not exist | Implemented |
| S011-EC-005 | Edge Case | P2 | Expected test file does not exist | Implemented |
| S011-EC-006 | Edge Case | P2 | Report directory already exists | Implemented |
| S011-EC-007 | Edge Case | P2 | Coverage target is a directory rather than a file | Implemented |
| S011-EC-008 | Edge Case | P2 | No expected tests are discovered | Implemented |
| S011-EC-009 | Edge Case | P1 | Runner process times out | Implemented |
| S011-EC-010 | Edge Case | P1 | Report output target is destructive or unowned | Implemented |
| S011-EC-011 | Edge Case | P1 | Fixed temporary config path already exists | Implemented |
| S011-EC-012 | Edge Case | P1 | Manifest report path escapes the report root | Implemented |
| S011-EC-013 | Edge Case | P1 | Coverage artifact is unowned | Implemented |
| S011-EC-014 | Edge Case | P1 | Artifact path has a symlink ancestor | Implemented |
| S011-EC-015 | Edge Case | P1 | Coverage target overlaps report output | Implemented |
| S011-SC-001 | Success | P1 | Generated report set is consumable by `parity check` | Implemented |
| S011-SC-002 | Success | P1 | Same input project produces deterministic manifest contents | Implemented |
| S011-SC-003 | Success | P1 | One failing individual test causes command failure | Implemented |
| S011-SC-004 | Success | P1 | Generated reports preserve file-local attribution needed for matched coverage | Implemented |
| S011-SC-005 | Success | P1 | Failed generation preserves the last complete report set | Implemented |
| S011-SC-006 | Success | P1 | Unsafe paths cannot delete unrelated data | Implemented |
| S011-SC-007 | Success | P1 | Report parsing remains contained beneath `reports/` | Implemented |
| S011-SC-008 | Success | P1 | Runner artifacts are removed after every attempt | Implemented |

## Cross-Spec Dependencies

- **Depends on:** S001 (CLI command invocation and exit-code behavior), S003 (coverage readers and normalized attribution structures), S006 (configuration and structure discovery), S008 (output format behavior of delegated `parity check`)
- **Required by:** S003 (Parity per-test report directory is a supported reader input)
- **Required by:** S010 (samples and CI may use `parity test` to generate attribution-capable artifacts)
