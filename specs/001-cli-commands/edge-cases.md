# S001 Edge Cases

| ID | Scenario | Expected Behavior |
|----|----------|-------------------|
| S001-EC-001 | `parity.yaml` does not exist in cwd and `--config` not provided | Print error: `parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.` Return exit code 1. |
| S001-EC-002 | `parity.yaml` exists but `file_get_contents()` returns false (unreadable) | Print error: `Could not read config: {path}`. Return exit code 1. |
| S001-EC-003 | `parity.yaml` contains invalid YAML (malformed syntax) | Symfony Yaml parser throws `ParseException`. The exception **MUST** propagate or be caught and reported. Return exit code 1. |
| S001-EC-004 | All `coverage_xml` candidate paths resolve to nonexistent files/directories | Print error: `No coverage file or directory found (tried: {comma-separated list}).` Return exit code 1. |
| S001-EC-005 | `structure` key is present but its value is an empty array | Print warning: `No structure entries in parity.yaml.` Return exit code 0 (success -- no structures means no violations). |
| S001-EC-006 | A structure entry's source path points to a directory that does not exist | Print warning: `Source path does not exist: {path}`. Skip the structure entry. Continue processing remaining structures. Do not treat as failure. |
| S001-EC-007 | A source directory exists but contains no files matching `source_extension` | Produce an empty table for that structure (no file rows). The structure does not contribute any failures. |
| S001-EC-008 | `--config` flag points to a file that does not exist (`realpath()` returns false) | Print error: `parity.yaml not found. Run from project root, place parity.yaml there, or use --config=path.` Return exit code 1. Same error as S001-EC-001 -- the command does not distinguish between missing cwd config and missing `--config` target. |
| S001-EC-009 | `getcwd()` returns false (e.g., cwd has been deleted) during `check` without `--config` | `resolveProjectRoot()` returns null. Print S001-FR-002.c error. Return exit code 1. |
| S001-EC-010 | `--format` is given with an invalid value (e.g., `--format=csv`) | The value is not `json`, so `$isJson` is `false`. The command falls through to table mode. No explicit validation error is raised. |
| S001-EC-011 | `getcwd()` returns false during `init` command | Print error: `Could not determine current directory.` Return exit code 1. |
| S001-EC-012 | `parity init` runs in a directory where the user lacks write permissions | `file_put_contents()` returns false. Print error: `Could not write parity.yaml.` Return exit code 1. |
| S001-EC-013 | Plugins produce warnings while `--format=json` is active | Plugin warnings **MUST NOT** be printed to stdout. The JSON output remains clean. Warnings are silently suppressed. |
| S001-EC-014 | `symfony/yaml` package is not installed | Print error: `Install symfony/yaml to use parity.yaml, or use a PHP config.` Return exit code 1. `loadConfig()` checks `class_exists(Yaml::class)` before parsing. |
