#!/bin/sh
# Forced command for the GitHub Actions key.
#
# The Actions key is a credential living on someone else's servers, so it gets
# no shell. It can do exactly one thing: hand us a delta on stdin, which we
# store and import. Anything else is refused. Even a fully leaked key cannot
# read, overwrite or delete anything here.

set -e

case "$SSH_ORIGINAL_COMMAND" in
  upload-delta)
    mkdir -p "$HOME/incoming"
    target="$HOME/incoming/delta-$(date -u +%Y%m%dT%H%M%SZ)-$$.ndjson"
    cat > "$target"
    if [ ! -s "$target" ]; then
      rm -f "$target"
      echo "refused: empty delta" >&2
      exit 1
    fi
    echo "received $(wc -l < "$target") lines"
    exec /usr/bin/php "$HOME/ops/import.php"
    ;;
  *)
    echo "refused: this key may only run 'upload-delta'" >&2
    exit 1
    ;;
esac
