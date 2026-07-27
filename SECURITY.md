# Security Policy

## Supported Versions

Security fixes target the latest released version of `testparity/parity`.

| Version | Supported |
| --- | --- |
| Latest stable | Yes |
| Older releases | Best effort |

## Reporting a Vulnerability

Do not open a public GitHub issue for a suspected vulnerability.

Use GitHub's private vulnerability reporting for `testparity/cli` when available, or contact the maintainers privately with:

- The affected version from `parity --version`.
- The minimal `parity.yaml` and command needed to reproduce.
- A description of the impact.
- Any relevant coverage artifact shape, with secrets removed.

## Security Scope

Relevant issues include arbitrary file reads, command execution, unsafe plugin loading behavior, path traversal, dependency confusion, or leaks of sensitive paths or environment values through output.

`parity check` reads project files and coverage artifacts without running tests. `parity test` intentionally executes the configured `test.command` through the system shell once per discovered belonging test. Placeholder values are shell-escaped, but the command template itself is trusted executable configuration.

Only run `parity test` with a reviewed `parity.yaml`, especially in CI or on pull requests from forks. Treat project-local and global plugins as trusted PHP code, and never load unreviewed plugins in a privileged environment.

Coverage reports are parsed as untrusted data. Avoid publishing reports that contain sensitive source paths, test names, or environment-specific information.
