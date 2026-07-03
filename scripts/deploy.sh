#!/usr/bin/env bash
# Deploy TangoCash from local → Helios.
#
# What's deployed:
#   - Every .php, .css, .js, image, and SQL migration under the tangocash dir
#   - Skips: legacy/playground files (index_v*, _header_v[2-5].php, tangocash_v[2-5].css),
#     .bak files, .DS_Store, and the scripts/ dir itself
#
# What it does:
#   - rsync over ssh (only sends changed files)
#   - sets ownership to tangocash:tangocash on the server
#   - prints a one-line summary
#
# Usage:
#   ./scripts/deploy.sh              # deploy everything
#   ./scripts/deploy.sh path/to/file # deploy a single file (relative to repo root)
#
# Requires: rsync, ssh root@108.61.206.103 working from the calling shell.

set -euo pipefail

REMOTE="root@108.61.206.103"
REMOTE_DIR="/home/tangocash/htdocs/tangocash.etonica.com"
LOCAL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$LOCAL_DIR"

if [ $# -gt 0 ]; then
  # Single-file deploy. Useful for "just push tangocash.css".
  for f in "$@"; do
    if [ ! -f "$f" ]; then
      echo "× not a file: $f" >&2
      exit 2
    fi
    echo "→ Pushing $f"
    rsync -az "$f" "${REMOTE}:${REMOTE_DIR}/$f"
    ssh "$REMOTE" "chown tangocash:tangocash ${REMOTE_DIR}/$f"
  done
  echo "✓ Deploy complete."
  exit 0
fi

# Full sync. (macOS ships rsync 2.6.9 — no --chown — so we chown after.)
echo "→ Syncing $LOCAL_DIR → $REMOTE:$REMOTE_DIR"
rsync -az --delete-after \
  --exclude='.git' \
  --exclude='.DS_Store' \
  --exclude='scripts/' \
  --exclude='*.bak' \
  --exclude='index_v1_legacy.php' \
  --exclude='index_v2.php' \
  --exclude='index_v3.php' \
  --exclude='index_v4.php' \
  --exclude='index_v5.php' \
  --exclude='index_v6.php' \
  --exclude='_header_v2.php' \
  --exclude='_header_v3.php' \
  --exclude='_header_v4.php' \
  --exclude='_header_v5.php' \
  --exclude='css/tangocash_v2.css' \
  --exclude='css/tangocash_v3.css' \
  --exclude='css/tangocash_v4.css' \
  --exclude='css/tangocash_v5.css' \
  ./ "$REMOTE:$REMOTE_DIR/"

ssh "$REMOTE" "chown -R tangocash:tangocash $REMOTE_DIR"

echo "✓ Deploy complete."
