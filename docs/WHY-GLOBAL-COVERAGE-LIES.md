# Why Global Coverage Lies

Global coverage answers a broad question: "Did tests execute enough of the project?"

Parity answers a stricter question: "Does each file's own matching test cover the file it is supposed to protect?"

## The Problem

A project can report 80% global coverage while still hiding weak direct tests:

| Metric | Result |
| --- | ---: |
| Project/global coverage | 80% |
| File coverage from all tests | 70% |
| Coverage from the matching test file | 40% |
| Incidental covering tests | 2 |

The 70% file coverage can look acceptable because integration, smoke, or workflow tests touched the same lines. That does not prove the matching unit or feature test owns the behavior.

## How Parity Shows It

`minimum-coverage` reads all-test file coverage:

```yaml
- minimum-coverage:
    min: 70
```

`matched-coverage` reads only lines covered by the expected matching test file:

```yaml
- matched-coverage:
    min: 40
```

`coverage-attribution` reports how many tests covered the file and how many of those were not the matching test.

In JSON output, the weak file intentionally looks like this:

```json
{
  "minimum-coverage": { "value": "70%" },
  "matched-coverage": { "value": "40%" },
  "coverage-attribution": { "value": "3|2" }
}
```

`3|2` means three tests covered at least one line in the file, and two of them were incidental non-matching tests.

## What To Do With This Signal

Treat high global coverage as a baseline, not a release guarantee. Use Parity to find files whose direct tests are thin even though broad test suites make aggregate coverage look healthy.
