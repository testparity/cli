#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
EXPECTED_VERSION="${1:-}"
VERSION_FILE="$PROJECT_DIR/VERSION"
CHANGELOG_FILE="$PROJECT_DIR/CHANGELOG.md"

if [[ ! "$EXPECTED_VERSION" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "ERROR: Expected release version must use vMAJOR.MINOR.PATCH format." >&2
    exit 1
fi

if [[ ! -f "$VERSION_FILE" ]]; then
    echo "ERROR: VERSION file not found at $VERSION_FILE" >&2
    exit 1
fi

if [[ ! -f "$CHANGELOG_FILE" ]]; then
    echo "ERROR: CHANGELOG.md not found at $CHANGELOG_FILE" >&2
    exit 1
fi

TRACKED_VERSION=$(tr -d '[:space:]' < "$VERSION_FILE")
if [[ "$TRACKED_VERSION" != "$EXPECTED_VERSION" ]]; then
    echo "ERROR: VERSION contains $TRACKED_VERSION but the release tag is $EXPECTED_VERSION." >&2
    echo "Prepare releases with dev/release-version.sh before pushing a tag." >&2
    exit 1
fi

VERSION_NUMBER="${EXPECTED_VERSION#v}"
RELEASE_BODY=$(awk -v prefix="## [$VERSION_NUMBER] - " '
    index($0, prefix) == 1 {
        capture = 1
        next
    }
    capture && /^## \[/ {
        exit
    }
    capture {
        print
    }
' "$CHANGELOG_FILE")

if [[ -z "$RELEASE_BODY" ]] || ! grep -Eq '^- .+' <<< "$RELEASE_BODY"; then
    echo "ERROR: CHANGELOG.md has no release notes for $EXPECTED_VERSION." >&2
    exit 1
fi

if grep -Fq -- '- (new release)' <<< "$RELEASE_BODY"; then
    echo "ERROR: CHANGELOG.md still contains placeholder notes for $EXPECTED_VERSION." >&2
    exit 1
fi

echo "Release metadata verified for $EXPECTED_VERSION"
