#!/usr/bin/env bash
# Push the site and the server-side ops scripts to DreamHost.
#   tools/deploy.sh [--dry-run]
set -euo pipefail

HOST="${OFT_HOST:-dh_pmtbvy@pdx1-shared-a4-06.dreamhost.com}"
KEY="${OFT_KEY:-$HOME/.ssh/outfortender_deploy}"
WEB_ROOT="${OFT_WEB_ROOT:-outfortender.com}"
HERE="$(cd "$(dirname "$0")/.." && pwd)"

DRY=""
[[ "${1:-}" == "--dry-run" ]] && DRY="--dry-run"

SSH="ssh -i $KEY -o BatchMode=yes -p 22"

echo "==> web/ -> ~/$WEB_ROOT/"
rsync -az --delete $DRY -e "$SSH" \
  --exclude '.DS_Store' \
  "$HERE/web/" "$HOST:$WEB_ROOT/"

echo "==> ops/ -> ~/ops/"
rsync -az $DRY -e "$SSH" \
  --exclude '.DS_Store' \
  "$HERE/ops/" "$HOST:ops/"

echo "==> ingest/ -> ~/ingest/"
rsync -az --delete $DRY -e "$SSH" \
  --exclude '__pycache__' --exclude '.DS_Store' \
  "$HERE/ingest/" "$HOST:ingest/"

if [[ -z "$DRY" ]]; then
  $SSH "$HOST" 'mkdir -p ~/incoming ~/data ~/ops/log && chmod 755 ~/data'
  echo "==> deployed"
fi
