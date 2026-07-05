# S009-DR-002: Markdown Source as Canonical Content

**Date**: 2026-04-24

### Decision

`parity-website/guide/` is the canonical source of all website guide content. `parity-website/.vitepress/` is the VitePress configuration. The `parity-website/.vitepress/dist/` directory is a build artifact, not a source of truth.

### Rationale

Having markdown files as the canonical source enables:
- Markdown-first editing (no VitePress-specific syntax required)
- Easy content reuse (same files could be used for other renderers)
- Clear separation between content and presentation

The VitePress config in `parity-website/.vitepress/config.ts` renders the real Markdown files under `parity-website/guide/`.

### Consequences

- **Build step required**: `parity-website/` source must be built by VitePress before deployment.
- **Dist is derived**: the `dist/` directory is regenerated on every build and should not be edited manually.
- **Content lives in the website repo**: all new website guides are created in `parity-website/guide/`.
