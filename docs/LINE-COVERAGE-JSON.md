# JSON line evidence

`parity check --format=json` preserves the existing report fields and adds `lineCoverage` per file:

```json
{
  "version": 1,
  "complete": true,
  "sourceHash": "<SHA256 of raw source bytes>",
  "lineHashes": ["<SHA256 of physical line 1>", "<SHA256 of physical line 2>"],
  "executableLines": [2],
  "coveredLines": [2]
}
```

Line numbers are one-based, unique, sorted integers. `coveredLines` counts only the configured associated test, using its exact configured identifier (optional `::method` and Pest `P\` prefix removed), never similarly named tests or incidental coverage from other tests. If the associated test file is missing, covered lines are empty when the remaining artifact evidence is complete.

`lineHashes` includes every physical source line, including comments and blank lines, excluding its CRLF/LF/CR terminator. A terminal newline does not add an extra empty line; an empty file has no lines. Whitespace and other line content are preserved before SHA256 hashing. `sourceHash` hashes the unmodified file bytes. No raw source is included. Consumers can align unchanged lines across shifted source files using these hashes; repeated identical lines require conservative handling.

`complete: true` requires a readable source file, full executable line metadata within its bounds, and test-attributed coverage. Per-test collections also require valid reports, the associated report when its test file exists, and matching executable line signatures. Complete zero-executable files have empty executable/covered arrays.

When evidence is unavailable or inconsistent, `complete` is false and `reason` explains why. Source hashes are included when readable, but executable/covered arrays are omitted and must not be interpreted as zero coverage. Aggregate Clover coverage alone cannot prove associated coverage. Percentages never substitute for line evidence. Completeness describes the supplied artifacts; callers must generate fresh coverage against the source checkout before comparing branches.
