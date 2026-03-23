#!/usr/bin/env bash
#
# Lädt Team-Wappen von https://crests.football-data.org herunter.
# Dateien: 1.svg, 2.svg, ... bis MAX_ID
# Ablage: images/crests/
#
# Verwendung:
#   chmod +x download-crests.sh
#   ./download-crests.sh [max_id]   # Standard: 1000
#

set -euo pipefail

BASE_URL="https://crests.football-data.org"
MAX_ID="${1:-1000}"
DEST_DIR="$(dirname "$0")/crests"

mkdir -p "$DEST_DIR"

echo "Lade Wappen 1–${MAX_ID} nach ${DEST_DIR} ..."
echo ""

success=0
skipped=0

for i in $(seq 1 "$MAX_ID"); do
  file="${i}.svg"
  dest="${DEST_DIR}/${file}"

  # Bereits vorhandene Dateien überspringen
  if [[ -f "$dest" ]]; then
    ((skipped++)) || true
    continue
  fi

  # curl: HTTP-Statuscode prüfen, bei 404 Datei nicht speichern
  http_code=$(curl --silent --max-time 5 --write-out "%{http_code}" \
                   --output "$dest" \
                   "${BASE_URL}/${file}")

  if [[ "$http_code" == "200" ]] && [[ -s "$dest" ]]; then
    ((success++)) || true
    echo "  ✓ ${file}"
  else
    rm -f "$dest"
  fi
done

echo ""
echo "Fertig: ${success} heruntergeladen, ${skipped} bereits vorhanden."
