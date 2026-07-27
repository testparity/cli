#!/usr/bin/env bash
# Release workflow script for Parity
#
# Usage:
#   ./dev/release-version.sh patch --dry-run   # preview
#   ./dev/release-version.sh patch --push      # bump + commit + tag + push
#   ./dev/release-version.sh minor --push
#   ./dev/release-version.sh major --push
#
# Add at least one release-note bullet beneath ## [Unreleased] before running.

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

VERSION=$(tr -d '[:space:]' < "$VERSION_FILE")
CHANGELOG_FILE="$PROJECT_DIR/CHANGELOG.md"

# Parse current version
if [[ ! "$VERSION" =~ ^v?([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    echo "ERROR: Invalid VERSION format: $VERSION" >&2
    exit 1
fi

MAJOR=${BASH_REMATCH[1]}
MINOR=${BASH_REMATCH[2]}
PATCH=${BASH_REMATCH[3]}

BUMP_TYPE="patch"
DRY_RUN=false
PUSH=false

for arg in "$@"; do
    case $arg in
        --dry-run) DRY_RUN=true ;;
        --push) PUSH=true ;;
        patch|minor|major) BUMP_TYPE=$arg ;;
        *)
            echo "ERROR: Unknown argument: $arg" >&2
            exit 1
            ;;
    esac
done

if $DRY_RUN && $PUSH; then
    echo "ERROR: --dry-run and --push cannot be used together." >&2
    exit 1
fi

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
NEW_VERSION_NUMBER="${NEW_MAJOR}.${NEW_MINOR}.${NEW_PATCH}"
TODAY=$(date '+%Y-%m-%d')

echo "Current version: $VERSION"
echo "Bump type: $BUMP_TYPE"
echo "New version: $NEW_VERSION"

UNRELEASED_BODY=$(awk '
    /^## \[Unreleased\]$/ {
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

if [[ -z "$UNRELEASED_BODY" ]] || ! grep -Eq '^- .+' <<< "$UNRELEASED_BODY"; then
    echo "ERROR: Add release notes beneath ## [Unreleased] in CHANGELOG.md before releasing." >&2
    exit 1
fi

if $DRY_RUN; then
    echo "[DRY RUN] Would update VERSION: $VERSION -> $NEW_VERSION"
    echo "[DRY RUN] Would promote the Unreleased notes to $NEW_VERSION_NUMBER on $TODAY"
    echo "[DRY RUN] Would create a git commit and annotated tag"
    echo "[DRY RUN] A real run with --push would publish both"
    exit 0
fi

CURRENT_BRANCH=$(git branch --show-current)
if [[ "$CURRENT_BRANCH" != "main" ]]; then
    echo "ERROR: Releases must be prepared from main; current branch is ${CURRENT_BRANCH:-detached HEAD}." >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: Working tree is dirty. Commit or stash changes before releasing." >&2
    exit 1
fi

if git rev-parse --verify --quiet "refs/tags/$NEW_VERSION" >/dev/null; then
    echo "ERROR: Tag $NEW_VERSION already exists." >&2
    exit 1
fi

# 1. Update VERSION
echo "$NEW_VERSION" > "$VERSION_FILE"
echo "Updated VERSION: $NEW_VERSION"

# 2. Promote the curated Unreleased notes into the new version.
CHANGELOG_TMP=$(mktemp)
awk -v version="$NEW_VERSION_NUMBER" -v date="$TODAY" '
    /^## \[Unreleased\]$/ && ! promoted {
        print "## [Unreleased]"
        print ""
        print "## [" version "] - " date
        promoted = 1
        next
    }
    {
        print
    }
' "$CHANGELOG_FILE" > "$CHANGELOG_TMP"
mv "$CHANGELOG_TMP" "$CHANGELOG_FILE"
echo "Updated CHANGELOG.md"

# 3. Verify the exact metadata that CI and Packagist will publish.
bash "$PROJECT_DIR/dev/verify-release-metadata.sh" "$NEW_VERSION"

# 4. Git commit
git add VERSION CHANGELOG.md
git commit -m "release: $NEW_VERSION"

# 5. Git tag
git tag -a "$NEW_VERSION" -m "Release $NEW_VERSION"

# 6. Push
if $PUSH; then
    echo "Pushing to remote..."
    git push --atomic origin main "$NEW_VERSION"
    echo "Released $NEW_VERSION"
else
    echo "[--push not specified] Skipping remote push"
    echo "To publish this prepared release, run: git push --atomic origin main $NEW_VERSION"
fi
