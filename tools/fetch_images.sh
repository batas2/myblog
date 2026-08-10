#!/usr/bin/env bash
# fetch_images.sh — pull one licensed header image per post from Openverse.
# Prefers cc0/pdm, then by, then by-sa; wide, large photographs only.
# Writes assets/img/posts/<slug>.jpg, the 480px .thumb.jpg the lists load,
# and merges the licence into assets/img/posts/credits.json.
#
# Usage:
#   ./tools/fetch_images.sh          fetch only the posts that have no image yet
#   ./tools/fetch_images.sh --force  re-fetch everything in the query map
#
# Adding a post means adding one line to Q below and running it. Posts that
# already have an image are skipped: Openverse results drift between runs, so
# a plain re-run must never swap out the picture a published essay is using.
set -u
cd "$(dirname "$0")/.."
mkdir -p assets/img/posts
UA="bfrackowiak.pl-blog/1.0 (kontakt@bfrackowiak.pl)"
FORCE=0
[ "${1:-}" = "--force" ] && FORCE=1
IM="$(command -v magick || command -v convert || true)"

declare -A Q=(
  [mathematicians-vs-lawyers]="mathematics chalkboard equations|blackboard math"
  [data-driven-theater]="theater stage red curtain|theatre stage curtain"
  [decisions-have-an-architecture-too]="highway interchange aerial|road junction aerial view"
  [nobody-owns-the-sum]="empty conference room|empty meeting room chairs"
  [diagrams-are-conversations-not-documentation]="whiteboard meeting sketch|whiteboard brainstorm"
  [the-migration-that-never-starts]="flock of migrating birds sky|bird migration flock"
  [the-anti-corruption-layer-is-for-people-too]="rope suspension footbridge|rope bridge canyon"
  [questions-are-an-architects-sharpest-tool]="question mark sign|question mark neon"
  [adrs-for-an-audience-of-one]="writing notebook fountain pen|journal writing desk"
  [edge-cases-written-by-parliament]="parliament chamber interior|parliament building hall"
  [anti-personas-the-users-i-refuse-to-serve]="no entry sign street|do not enter sign"
  [pricing-is-architecture]="price tags market|price tag label"
  [the-diagram-that-cannot-lie]="architectural blueprint drawing|blueprint plans"
  [a-company-of-one-staffed-by-agents]="home office desk lamp night|working late desk computer"
  [grinding-dijkstra-at-senior-level]="hedge maze aerial|garden labyrinth maze"
  [from-signals-to-blips]="radar screen|air traffic control radar"
  [probabilistic-metrics]="dice game chance|rolling dice"
  [prompt-driven-architecture]="computer terminal code screen|command line programming"
  [multi-agent-is-an-org-chart]="starling murmuration|flock of starlings sky"
  [pricing-the-invisible]="iceberg ocean|iceberg"
  [the-model-is-the-easy-part]="revolving door glass entrance|turnstile gate entrance"
  [a-feature-is-not-a-service]="library card catalog drawers|card catalog wooden drawers"
  [design-the-stopping-point]="unfinished bridge construction river|incomplete bridge span"
  [count-your-rejections]="no vacancy neon sign|vacancy sign motel night"
  # a desire path, not cables: the essay is about the worn shortcut beating the
  # paved route, and "patch panel" was illustrating the noun rather than the argument
  [adoption-is-the-deliverable]="desire path grass|worn path beside paved footpath"
  [the-bottleneck-moved]="stack of paper documents pile|paperwork stacked desk office"
  [we-deleted-the-wrong-half-of-agile]="scaffolding supporting building facade|scaffolding old building front"
)

TMP=$(mktemp -d)
: > "$TMP/credits.tsv"
ok=0; skip=0; fail=""

pick() { # $1 = json file -> tsv: url creator license landing title
  jq -r '
    [.results[]
     | select(.width >= 900 and .height >= 450 and (.width > .height))
     | {url, creator, license, landing: .foreign_landing_url, title, w: .width}]
    | (map(select(.license=="cc0" or .license=="pdm"))
       + map(select(.license=="by")))
    | .[0] // empty
    | [.url, (.creator // "unknown"), .license, (.landing // .url), (.title // "")] | @tsv' "$1" 2>/dev/null
}

for slug in "${!Q[@]}"; do
  if [ "$FORCE" -eq 0 ] && [ -f "assets/img/posts/$slug.jpg" ]; then
    skip=$((skip+1)); continue
  fi
  IFS='|' read -r q1 q2 <<< "${Q[$slug]}"
  hit=""
  for q in "$q1" "$q2"; do
    enc=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$q")
    curl -s -A "$UA" "https://api.openverse.org/v1/images/?q=${enc}&license_type=commercial&aspect_ratio=wide&size=large&category=photograph&per_page=20" -o "$TMP/r.json"
    hit=$(pick "$TMP/r.json")
    [ -n "$hit" ] && break
    # relax: drop size/category constraints
    curl -s -A "$UA" "https://api.openverse.org/v1/images/?q=${enc}&license_type=commercial&aspect_ratio=wide&per_page=20" -o "$TMP/r.json"
    hit=$(pick "$TMP/r.json")
    [ -n "$hit" ] && break
    sleep 1
  done
  if [ -z "$hit" ]; then fail="$fail $slug"; continue; fi
  IFS=$'\t' read -r url creator license landing title <<< "$hit"
  if curl -sL -A "$UA" --max-time 60 "$url" -o "assets/img/posts/$slug.jpg" \
     && [ "$(stat -c%s "assets/img/posts/$slug.jpg")" -gt 25000 ] \
     && file "assets/img/posts/$slug.jpg" | grep -qiE 'jpeg|png|image'; then
    printf '%s\t%s\t%s\t%s\t%s\n' "$slug" "$creator" "$license" "$landing" "$title" >> "$TMP/credits.tsv"
    # the lists and the related-posts rail load the thumbnail, not the hero
    [ -n "$IM" ] && "$IM" "assets/img/posts/$slug.jpg" -resize 480x -strip -quality 82 \
      "assets/img/posts/$slug.thumb.jpg" 2>/dev/null
    ok=$((ok+1)); echo "OK  $slug  ($license, $creator)"
  else
    rm -f "assets/img/posts/$slug.jpg"; fail="$fail $slug"
  fi
  sleep 1
done

# merge, never replace: a run that fetches one new post must not drop the
# licence lines for every other image on the site (they are a CC BY obligation)
CREDITS=assets/img/posts/credits.json
[ -s "$CREDITS" ] || echo '{}' > "$CREDITS"
jq -R -s 'split("\n") | map(select(length>0) | split("\t")
  | {(.[0]): {creator: .[1], license: .[2], source: .[3], title: .[4]}}) | add // {}' \
  "$TMP/credits.tsv" > "$TMP/new.json"
jq -s '.[0] * .[1]' "$CREDITS" "$TMP/new.json" > "$TMP/merged.json" && mv "$TMP/merged.json" "$CREDITS"
rm -rf "$TMP"
echo "----"
echo "downloaded: $ok  skipped (already had an image): $skip  of ${#Q[@]} in the map"
[ -n "$fail" ] && echo "missing:$fail"
[ -z "$IM" ] && echo "note: no ImageMagick — thumbnails not generated"
exit 0
