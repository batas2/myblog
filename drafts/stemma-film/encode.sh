#!/usr/bin/env bash
# Encodes a rendered frame sequence into the two files the page serves.
#
#   ./encode.sh <light|dark>
#
# Silent by design, so -an throughout. faststart matters: the page starts playing before the
# whole file has arrived, and without the moov atom up front it cannot.
#
# The CRFs are the loosest that still hold the app's own 12px chrome. They were picked by encoding
# the 10 hardest seconds — fine UI text in motion during the modelling beat — across a ladder and
# reading the result at 2× magnification. One step looser on either codec starts banding the dark
# canvas; tighter buys nothing you can see and doubles the file.
set -euo pipefail

THEME="${1:?usage: encode.sh <light|dark>}"
SP="${STEMMA_FILM_WORK:-/tmp/stemma-film}"
FRAMES="$SP/film/frames-$THEME"
OUT="$(cd "$(dirname "$0")/../.." && pwd)/assets/video"   # the site's assets/, two levels up
mkdir -p "$OUT"

n=$(find "$FRAMES" -name 'f*.png' | wc -l)
echo "==> $THEME: $n frames"

ffmpeg -y -loglevel error -framerate 30 -i "$FRAMES/f%05d.png" \
  -c:v libx264 -preset veryslow -crf 29 -profile:v high -level 4.0 \
  -pix_fmt yuv420p -g 60 -movflags +faststart -an \
  "$OUT/stemma-film-$THEME.mp4"

ffmpeg -y -loglevel error -framerate 30 -i "$FRAMES/f%05d.png" \
  -c:v libvpx-vp9 -crf 41 -b:v 0 -row-mt 1 -deadline good -cpu-used 2 \
  -pix_fmt yuv420p -g 60 -an \
  "$OUT/stemma-film-$THEME.webm"

for f in "$OUT/stemma-film-$THEME.mp4" "$OUT/stemma-film-$THEME.webm"; do
  printf '    %-46s %8s  %s\n' "$(basename "$f")" \
    "$(du -h "$f" | cut -f1)" \
    "$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$f")s"
done
