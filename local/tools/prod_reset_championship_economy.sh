#!/usr/bin/env bash
#
# Сброс экономики ЧМ-2026 и пересчёт матчей на проде (Linux).
#
#   cd /path/to/site
#   bash local/tools/prod_reset_championship_economy.sh 7           # dry-run
#   bash local/tools/prod_reset_championship_economy.sh 7 --execute
#
# PHP_BIN=/usr/bin/php8.2 bash local/tools/prod_reset_championship_economy.sh 7 --execute

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PHP="${PHP_BIN:-php}"
EVENT_ID=63849
FROM=1
TO="${1:-}"
EXECUTE=false

if [[ "${2:-}" == "--execute" ]]; then
  EXECUTE=true
fi

if [[ -z "$TO" ]] || ! [[ "$TO" =~ ^[0-9]+$ ]] || [[ "$TO" -lt 1 ]]; then
  echo "Usage: bash local/tools/prod_reset_championship_economy.sh <lastMatchNumber> [--execute]"
  echo "Example: bash local/tools/prod_reset_championship_economy.sh 7 --execute"
  exit 1
fi

cd "$ROOT"

echo "Site root: $ROOT"
echo "PHP: $($PHP -v | head -n1)"
echo "Event: $EVENT_ID, matches #$FROM–#$TO"
echo ""

echo "=== Step 1/3: dry-run reset ==="
$PHP local/tools/reset_game_economy.php --dry-run
echo ""

if [[ "$EXECUTE" != true ]]; then
  echo "Dry-run only. To run for real:"
  echo "  bash local/tools/prod_reset_championship_economy.sh $TO --execute"
  exit 0
fi

echo "=== Step 2/3: reset economy ==="
$PHP local/tools/reset_game_economy.php --confirm
echo ""

echo "=== Step 3/3: recalc matches $FROM..$TO ==="
$PHP local/tools/recalc_matches_range.php "$EVENT_ID" "$FROM" "$TO"
echo ""

echo "Done. Check match cards: bet count should match prognosis count."
