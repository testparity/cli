#!/usr/bin/env bash
# Release workflow script for Parity
#
# Usage:
#   ./dev/release-version.sh patch --dry-run   # preview
#   ./dev/release-version.sh patch --push      # bump + commit + tag + push
#   ./dev/release-version.sh minor --push
#   ./dev/release-version.sh major --push

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_DIR"

# Load VERSION
VERSION_FILE="$PROJECT_DIR/VERSION"
if [[ ! -f "$VERSION_FILE" ]]; then
    echo "ERROR: VERSION file not found at $VERSION_FILE" >&2
    exit 1
fi

VERSION=$(cat "$VERSION_FILE" | tr -d '[:space:]')
CHANGELOG_FILE="$PROJECT_DIR/CHANGELOG.md"

# Parse current version
if [[ ! "$VERSION" =~ ^v?([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    echo "ERROR: Invalid VERSION format: $VERSION" >&2
    exit 1
fi

MAJOR=${BASH_REMATCH[1]}
MINOR=${BASH_REMATCH[2]}
PATCH=${BASH_REMATCH[3]}

BUMP_TYPE="${1:-patch}"
DRY_RUN=false
PUSH=false

for arg in "$@"; do
    case $arg in
        --dry-run) DRY_RUN=true ;;
        --push) PUSH=true ;;
        patch|minor|major) BUMP_TYPE=$arg ;;
    esac
done

# Compute new version
case $BUMP_TYPE in
    patch) NEW_PATCH=$((PATCH + 1)); NEW_MINOR=$MINOR; NEW_MAJOR=$MAJOR ;;
    minor) NEW_PATCH=0; NEW_MINOR=$((MINOR + 1)); NEW_MAJOR=$MAJOR ;;
    major) NEW_PATCH=0; NEW_MINOR=0; NEW_MAJOR=$((MAJOR + 1)) ;;
    *)
        echo "ERROR: Invalid bump type: $BUMP_TYPE (must be patch, minor, or major)" >&2
        exit 1
        ;;
esac

NEW_VERSION="v${NEW_MAJOR}.${NEW_MINOR}.${NEW_PATCH}"
TODAY=$(date '+%Y-%m-%d')

echo "Current version: $VERSION"
echo "Bump type: $BUMP_TYPE"
echo "New version: $NEW_VERSION"

if $DRY_RUN; then
    echo "[DRY RUN] Would update VERSION: $VERSION → $NEW_VERSION"
    echo "[DRY RUN] Would update CHANGELOG.md date to $TODAY"
    echo "[DRY RUN] Would create git commit + tag + push"
    exit 0
fi

# 1. Update VERSION
echo "$NEW_VERSION" > "$VERSION_FILE"
echo "Updated VERSION: $NEW_VERSION"

# 2. Prepend new changelog entry
CHANGELOG_TMP=$(mktemp)
cat > "$CHANGELOG_TMP" << EOF
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [$NEW_VERSION] - $TODAY

### Added
- (new release)

EOF
tail -n +8 "$CHANGELOG_FILE" >> "$CHANGELOG_TMP"
mv "$CHANGELOG_TMP" "$CHANGELOG_FILE"
echo "Updated CHANGELOG.md"

# 3. Git commit
git add VERSION CHANGELOG.md
git commit -m "release: $NEW_VERSION"

# 4. Git tag
git tag -a "v${NEW_VERSION}" -m "Release v${NEW_VERSION}"

# 5. Push
if $PUSH; then
    echo "Pushing to remote..."
    git push origin main
    git push origin "v${NEW_VERSION}"
    echo "✅ Released v${NEW_VERSION}"
else
    echo "[--push not specified] Skipping remote push"
    echo "To release, run: ./dev/release-version.sh $BUMP_TYPE --push"
fi
