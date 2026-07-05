# Plugin System

Specs: S005

Parity plugins are trusted PHP code. Running `parity check` can load rule classes from:

For the full `RuleInterface` contract and plugin load behavior, see `docs/REFERENCE.md`.

| Source | Location |
|--------|----------|
| Project-local plugins | `.parity/plugins/*.php` |
| User-global plugins | `~/.parity/plugins/*.php` |
| Composer packages | `extra.parity.rules` in installed package metadata |

Only install or commit plugins from sources you trust. Public CI should review plugin code the same way it reviews application code.
