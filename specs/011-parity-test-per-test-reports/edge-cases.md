# S011 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S011-EC-001 | `--config` points to a file that does not exist or cannot be resolved | Print `parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.` Return exit code 1. |
| S011-EC-002 | A discovered test process exits non-zero | Print `Failed running test [{relative-test-path}]`, print any non-empty stderr/stdout from the runner, stop immediately, and return exit code 1. |
| S011-EC-003 | The runner succeeds but its artifact is missing or contains no executable source coverage | Print a coverage-artifact error for the relative test path, remove staging output, preserve the previous complete report set, and return exit code 1. |
| S011-EC-004 | A structure `source` directory does not exist | Skip that structure during discovery. Do not fail the command solely for the missing structure path. |
| S011-EC-005 | The expected test file derived from a source file does not exist | Skip that expected test file. Do not add it to the execution set. |
| S011-EC-006 | The target report directory already contains a valid older report set | Generate in a sibling staging directory and replace the older set only after every new report and manifest is complete. |
| S011-EC-007 | `test.coverage` resolves to a directory path rather than a file path | Create the directory before runner execution and remove any previous directory artifact before the run. |
| S011-EC-008 | No expected tests are discovered from any configured structure | Print `No expected tests discovered from the configured structures.` Return exit code 0. |
| S011-EC-009 | A runner exceeds `test.timeout` or `--timeout` | Stop the process, print the relative test path and timeout, remove staging output, preserve the previous complete report set, and return exit code 1. |
| S011-EC-010 | Report output resolves to a filesystem root, project root, home directory, `.parity` root, symlink, file, or unowned non-empty directory | Refuse the target before running any test or deleting any data. |
| S011-EC-011 | A user already has a file named `parity.test.yaml` | Leave it untouched; delegated checking uses a unique temporary file that is always removed. |
| S011-EC-012 | A manifest report path is absolute, contains `..`, or escapes `reports/` through a symlink | Ignore the report without reading it. |
| S011-EC-013 | An existing coverage target is outside `.parity/` and has no Parity ownership marker | Refuse to replace it before running the test. |
| S011-EC-014 | A report or coverage target has a symlink in an ancestor component | Refuse the target before creating, replacing, or deleting any artifact. |
| S011-EC-015 | The coverage target overlaps the report output directory | Refuse the coverage target before running the test so the previous complete report set remains untouched. |
