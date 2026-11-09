#!/usr/bin/env bash
# Writes templates/layouts/<slug>/design.json (templates/layouts/pro/<slug>/ when the slug's catalogue entry
# sets 'pro' => true, as LayoutTemplateCatalogue::relative_path() places it) from the tokens you chose, projected through the live
# LayoutDesign::schema(): every section and key, in schema order, tab-indented. A token the schema
# drops (unknown key, value outside its enum or range) is reported, so nothing is lost silently.
#
# Usage: design_json.sh <slug> <tokens.json>
#   tokens.json holds only the tokens you set, e.g. {"colours":{"background":"#ffffff"},"inputs":{"label":"floating"}}
#   WP: the wp-cli command; on Local it needs the site's socket, e.g.
#     WP='php -d mysqli.default_socket=".../mysqld.sock" /opt/homebrew/bin/wp' design_json.sh minimal-steps t.json
set -euo pipefail
slug=${1:?slug}; tokens=${2:?tokens.json}
root=$(git rev-parse --show-toplevel)
# The slug's entry in LayoutTemplateCatalogue::catalogue() runs from its 'slug' line to the line closing it, "],".
entry=$(awk -v slug="$slug" '$0 ~ "\x27slug\x27 *=> *\x27" slug "\x27" { on = 1 } on { print } on && /^ *\], *$/ { exit }' \
    "$root/includes/Controllers/LayoutTemplateCatalogue.php")
[ -n "$entry" ] || { echo "No catalogue entry for $slug in LayoutTemplateCatalogue::catalogue()" >&2; exit 1; }
folder="$slug"
if printf '%s\n' "$entry" | grep -qE "'pro' *=> *true"; then
    folder="pro/$slug"
fi
out="$root/templates/layouts/$folder/design.json"
mkdir -p "$(dirname "$out")"

YC_TOKENS="$(cat "$tokens")" eval "${WP:-wp} eval '
    \$in   = json_decode( getenv( \"YC_TOKENS\" ), true );
    \$meta = \YayCheckout\Models\LayoutDesign::from_meta( \$in )->to_meta();
    foreach ( (array) \$in as \$section => \$keys ) {
        foreach ( is_array( \$keys ) ? \$keys : [] as \$key => \$value ) {
            if ( ( \$meta[ \$section ][ \$key ] ?? null ) !== \$value ) {
                fwrite( STDERR, \"DROPPED \$section.\$key = \" . wp_json_encode( \$value ) . \"\n\" );
            }
        }
    }
    \$json = wp_json_encode( \$meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    echo preg_replace_callback( \"/^( +)/m\", function ( \$m ) { return str_repeat( \"\t\", strlen( \$m[1] ) / 4 ); }, \$json ), \"\n\";
'" > "$out"
echo "wrote $out"
