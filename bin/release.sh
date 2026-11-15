#!/bin/sh
# Publishes the dev branch to main in one step. Run it with `git release`
# (set up once per computer, see "Working on the project" in README.md) or `sh bin/release.sh`.
#
#   1. Stops if you have uncommitted changes.
#   2. Updates dev and main from GitHub, and stops if either has changed in a way that needs you.
#   3. Pushes dev, brings main up to dev, pushes main.
#   4. Leaves dev and main pointing at the same commit, and you back on dev.
#
# It never force-pushes and never resolves a conflict for you: if something needs a decision,
# it stops and says what to do.
set -eu

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nStopped: %s\n' "$*" >&2; exit 1; }
has_ref() { git show-ref --verify --quiet "$1"; }

cd "$(git rev-parse --show-toplevel)"

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    fail "you have uncommitted changes. Commit them on dev (or stash them), then run this again."
fi
if ! has_ref refs/heads/dev; then
    git fetch --quiet origin
    if has_ref refs/remotes/origin/dev; then
        fail "dev is on GitHub but not on this computer yet. Run: git switch dev"
    fi
    fail "there is no dev branch yet. Create it with: git switch -c dev main"
fi

step "Getting the latest dev and main from GitHub"
git fetch origin

step "Updating and pushing dev"
git switch dev
if has_ref refs/remotes/origin/dev; then
    git merge --ff-only origin/dev \
        || fail "dev on GitHub and your dev have both changed. Run 'git pull' on dev, fix any conflicts, commit, then run this again."
fi
git push -u origin dev

step "Bringing main up to dev"
git switch main
if has_ref refs/remotes/origin/main; then
    git merge --ff-only origin/main \
        || fail "main on GitHub and your main have both changed. Run 'git switch main' and 'git pull', fix any conflicts, then run this again."
fi
if [ -z "$(git rev-list main..dev)" ]; then
    git push origin main
    git switch dev
    echo
    echo "Nothing new to release: main already has everything on dev."
    exit 0
fi
# Fast-forwards when main has nothing of its own; otherwise makes a merge commit.
git merge --no-edit -m "Merge dev into main" dev \
    || fail "merging dev into main hit a conflict (you are on main). Fix the files git lists, then run: git add -A, git commit, git push, git switch dev, git merge main, git push."
git push origin main

step "Moving dev to the same commit as main"
git switch dev
git merge --ff-only main
git push origin dev

echo
echo "Released: main and dev are both at $(git rev-parse --short HEAD) on GitHub."
