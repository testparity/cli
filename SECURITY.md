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

Parity reads project files and coverage artifacts but does not run a project's test suite. Treat coverage reports, `parity.yaml`, and custom plugins as project-controlled input.
