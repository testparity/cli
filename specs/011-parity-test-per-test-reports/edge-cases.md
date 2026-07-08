# S011 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S011-EC-001 | `--config` points to a file that does not exist or cannot be resolved | Print `parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.` Return exit code 1. |
| S011-EC-002 | A discovered test process exits non-zero | Print `Failed running test [{relative-test-path}]`, print any non-empty stderr/stdout from the runner, stop immediately, and return exit code 1. |
| S011-EC-003 | The runner succeeds but the configured coverage artifact path does not exist afterwards | Normalization produces an empty report for that test, the manifest is still written, and any later structural failure is decided by delegated `parity check`. |
| S011-EC-004 | A structure `source` directory does not exist | Skip that structure during discovery. Do not fail the command solely for the missing structure path. |
| S011-EC-005 | The expected test file derived from a source file does not exist | Skip that expected test file. Do not add it to the execution set. |
| S011-EC-006 | The target report directory already exists with old JSON files | Remove the directory before writing the new report set so no stale report files remain. |
| S011-EC-007 | `test.coverage` resolves to a directory path rather than a file path | Create the directory before runner execution and remove any previous directory artifact before the run. |
| S011-EC-008 | No expected tests are discovered from any configured structure | Print `No expected tests discovered from the configured structures.` Return exit code 0. |

