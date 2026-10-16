#!/bin/bash
#
# YayCheckout Dev & Release Script
# Usage: ./scripts/run.sh [command]
#
# Commands:
#   dev                - Update admin shell + start Vite dev server (HMR on :3040) + blocks watcher
#   build              - Build admin dashboard + blocks for production
#   release [version]  - Create release ZIP package (prompts for version and WP/WC tested up to)
#   bump <version>     - Bump version number in plugin files
#   info               - Show current version info
#   clean              - Remove admin build artifacts (blocks output is left)
#   backlog            - List docs/backlog/ items and their open boxes
#   previews [mock]    - Capture templates/layouts/{,pro/}*/preview.html from the HTML mock
#   help               - Show this help message
#

set -e

# ─── Paths ──────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
ADMIN_DIR="$PLUGIN_DIR/apps/admin"
BLOCKS_DIR="$PLUGIN_DIR/packages/blocks"
RELEASE_DIR="$PLUGIN_DIR/release"
# Pro-only paths: the Free zip deletes them, except each preview.html (Free's picker shows Pro templates locked),
# and the artifact check enforces it. Space-separated, each ending in /.
# Owners: includes/Pro/ (YayCheckout::boot_pro()), assets/dist/blocks/pro/ (ProServiceProvider::built_blocks())
# and templates/layouts/pro/ (LayoutTemplateCatalogue::relative_path()).
PRO_PATHS="includes/Pro/ assets/dist/blocks/pro/ templates/layouts/pro/"
PLUGIN_SLUG="yaycheckout"
MAIN_FILE="$PLUGIN_DIR/$PLUGIN_SLUG.php"

if [ -z "$PLUGIN_DIR" ] || [ "$PLUGIN_DIR" = "/" ] || [ ! -f "$MAIN_FILE" ]; then
    echo "ERROR: Invalid plugin directory: '$PLUGIN_DIR'" >&2
    exit 1
fi

# ─── Output helpers ─────────────────────────────────────────────────────

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

print_msg()     { echo -e "${1}${2}${NC}"; }
print_step()    { print_msg "$BLUE" "→ $1"; }
print_success() { print_msg "$GREEN" "✓ $1"; }
print_error()   { print_msg "$RED" "✗ $1"; }
print_warning() { print_msg "$YELLOW" "⚠ $1"; }
print_info()    { print_msg "$CYAN" "ℹ $1"; }

# Portable in-place sed edit (macOS/Linux compatible)
sed_inplace() {
    local pattern=$1
    local file=$2
    if sed --version 2>&1 | grep -q GNU; then
        sed -i "$pattern" "$file"
    else
        sed -i '' "$pattern" "$file"
    fi
}

# ─── Toolchain helpers ──────────────────────────────────────────────────

# Resolve a working pnpm invocation into $PNPM.
# Some setups ship a broken corepack shim as `pnpm`; fall back to
# `corepack pnpm`, and as a last resort to the newest node under ~/.nvm.
resolve_pnpm() {
    if pnpm --version &> /dev/null; then
        PNPM="pnpm"
        return
    fi
    if corepack pnpm --version &> /dev/null; then
        PNPM="corepack pnpm"
        return
    fi
    local nvm_dir="$HOME/.nvm/versions/node"
    if [ -d "$nvm_dir" ]; then
        local newest
        newest=$(ls "$nvm_dir" | sort -V | tail -1)
        if [ -n "$newest" ]; then
            export PATH="$nvm_dir/$newest/bin:$PATH"
            if corepack pnpm --version &> /dev/null; then
                PNPM="corepack pnpm"
                print_warning "Using pnpm via corepack from node $newest"
                return
            fi
        fi
    fi
    print_error "pnpm is not available. Install it first: npm install -g pnpm"
    exit 1
}

install_admin_deps() {
    if [ ! -d "$ADMIN_DIR/node_modules" ]; then
        print_step "Installing admin app dependencies..."
        (cd "$ADMIN_DIR" && $PNPM install)
        print_success "Dependencies installed"
    fi
}

install_blocks_deps() {
    if [ ! -d "$BLOCKS_DIR/node_modules" ]; then
        print_step "Installing blocks dependencies..."
        (cd "$BLOCKS_DIR" && $PNPM install)
        print_success "Blocks dependencies installed"
    fi
}

# Admin Shell — install/update and regenerate vendor-prefixed via php-scoper.
# Pass "prerelease" to use the release variant of the shell updater when present.
update_shell() {
    local mode=${1:-dev}
    print_step "Check Admin Shell"
    cd "$PLUGIN_DIR"
    mkdir -p "$PLUGIN_DIR/vendor-prefixed/src"
    composer update
    local bin="$PLUGIN_DIR/vendor/bin/yaycommerce-update"
    if [ "$mode" = "prerelease" ] && [ -f "$PLUGIN_DIR/vendor/bin/yaycommerce-prerelease" ]; then
        bin="$PLUGIN_DIR/vendor/bin/yaycommerce-prerelease"
    fi
    if [ -f "$bin" ]; then
        "$bin"
        print_success "Admin Shell updated"
    else
        print_warning "Admin Shell updater not found, skipped"
    fi
}

# ─── Version helpers ────────────────────────────────────────────────────

get_version() {
    local version
    version=$(grep -E "define\( 'YAYCHECKOUT_VERSION'" "$MAIN_FILE" | sed -E "s/.*'([0-9]+\.[0-9]+\.[0-9]+[^']*)'.*/\1/")
    if [ -z "$version" ]; then
        print_error "Failed to extract version from $MAIN_FILE"
        exit 1
    fi
    echo "$version"
}

# Plugin release version: X.Y.Z, X.Y.Z.W or with a -tag suffix
validate_version() {
    [[ "$1" =~ ^[0-9]+(\.[0-9]+){2,3}(-[a-zA-Z0-9.]+)?$ ]]
}

# Escape characters that are special on the replacement side of sed
sed_escape() {
    echo "$1" | sed 's/[\/&]/\\&/g'
}

# Update version in main plugin file (header + constant) and readme.txt
update_version() {
    local new_version=$1
    local safe_version
    safe_version=$(sed_escape "$new_version")

    sed_inplace "s/^ \* Version: .*/ \* Version:           $safe_version/" "$MAIN_FILE"
    sed_inplace "s/define( 'YAYCHECKOUT_VERSION', '[^']*' )/define( 'YAYCHECKOUT_VERSION', '$safe_version' )/" "$MAIN_FILE"

    if [ -f "$PLUGIN_DIR/readme.txt" ]; then
        sed_inplace "s/^Stable tag: .*/Stable tag: $safe_version/" "$PLUGIN_DIR/readme.txt"
    fi

    print_success "Version updated to $new_version"
}

prompt_release_version() {
    local requested=$1
    local current_version
    current_version=$(get_version)

    if [ -n "$requested" ]; then
        update_version "$requested"
        return
    fi

    local new_version
    while true; do
        read -r -p "  Release version [$current_version]: " new_version
        new_version=${new_version:-$current_version}
        validate_version "$new_version" && break
        print_error "Invalid version. Use: X.Y.Z, X.Y.Z.W or X.Y.Z-tag"
    done

    if [ "$new_version" != "$current_version" ]; then
        update_version "$new_version"
    fi
}

# "Tested up to" lives in readme.txt for WordPress and in the plugin header for WooCommerce
get_wp_tested() {
    grep -E "^Tested up to:" "$PLUGIN_DIR/readme.txt" | sed -E "s/^Tested up to:[[:space:]]*//"
}

get_wc_tested() {
    grep -E "^ \* WC tested up to:" "$MAIN_FILE" | sed -E "s/^ \* WC tested up to:[[:space:]]*//"
}

# Tested-up-to version: X.Y or X.Y.Z
validate_tested_version() {
    [[ "$1" =~ ^[0-9]+(\.[0-9]+){1,2}$ ]]
}

update_wp_tested() {
    sed_inplace "s/^Tested up to: .*/Tested up to: $(sed_escape "$1")/" "$PLUGIN_DIR/readme.txt"
    print_success "WordPress tested up to $1"
}

update_wc_tested() {
    sed_inplace "s/^ \* WC tested up to: .*/ * WC tested up to: $(sed_escape "$1")/" "$MAIN_FILE"
    print_success "WooCommerce tested up to $1"
}

# Prompt for one tested-up-to value: $1 label, $2 current value, $3 update function
prompt_tested_version() {
    local label=$1
    local current=$2
    local updater=$3
    local value
    while true; do
        read -r -p "  $label tested up to [$current]: " value
        value=${value:-$current}
        validate_tested_version "$value" && break
        print_error "Invalid version. Use: X.Y or X.Y.Z"
    done

    if [ "$value" != "$current" ]; then
        "$updater" "$value"
    fi
}

# ─── Commands ───────────────────────────────────────────────────────────

cmd_dev() {
    update_shell
    resolve_pnpm
    install_admin_deps
    install_blocks_deps

    # Ctrl+C reaches both processes, but if Vite exits on its own the
    # watcher would outlive the script and keep rewriting assets/dist/blocks.
    print_step "Starting blocks watcher (assets/dist/blocks)..."
    (cd "$BLOCKS_DIR" && exec $PNPM start) &
    trap 'kill $(jobs -p) 2>/dev/null' EXIT

    print_info "Make sure YAYCHECKOUT_IS_DEVELOPMENT as true"
    print_step "Starting Vite dev server (http://localhost:3040)..."
    cd "$ADMIN_DIR"
    $PNPM dev
}

cmd_build() {
    resolve_pnpm
    install_admin_deps
    install_blocks_deps

    print_step "Building admin dashboard (typecheck + build)..."
    (cd "$ADMIN_DIR" && $PNPM build)
    print_success "Admin build complete! Output: $PLUGIN_DIR/assets/dist/admin/"

    # Blocks must build for every release: register_block_type() reads
    # assets/dist/blocks/<block>/block.json, so a ZIP built without this
    # step ships an editor with no YayCheckout blocks and no error saying why.
    print_step "Building blocks..."
    (cd "$BLOCKS_DIR" && $PNPM build)
    print_success "Blocks build complete! Output: $PLUGIN_DIR/assets/dist/blocks/"
}

cmd_release() {
    local no_prompt=false
    local requested_version=""
    local plan="free"
    local wp_tested=""
    local wc_tested=""
    local arg
    for arg in "$@"; do
        case $arg in
            --no-prompt)   no_prompt=true ;;
            --plan=*)      plan="${arg#--plan=}" ;;
            --wp-tested=*) wp_tested="${arg#--wp-tested=}" ;;
            --wc-tested=*) wc_tested="${arg#--wc-tested=}" ;;
            *) [ -z "$requested_version" ] && requested_version="$arg" ;;
        esac
    done

    # Free by default, and anything else fails, so a mistyped release never ships Pro code.
    if [ "$plan" != "free" ] && [ "$plan" != "pro" ]; then
        print_error "Invalid plan: $plan"
        echo "Use: --plan=free or --plan=pro"
        exit 1
    fi

    # Pro installs under its own slug: a wordpress.org update to 'yaycheckout' would
    # otherwise overwrite a paid Pro install. When both copies are active,
    # yaycheckout.php deactivates the second.
    local plan_slug="$PLUGIN_SLUG"
    local plan_label="YayCheckout"
    if [ "$plan" = "pro" ]; then
        plan_slug="${PLUGIN_SLUG}-pro"
        plan_label="YayCheckout Pro"
    fi

    if [ -n "$requested_version" ] && ! validate_version "$requested_version"; then
        print_error "Invalid version format: $requested_version"
        echo "Use: X.Y.Z, X.Y.Z.W or X.Y.Z-tag"
        exit 1
    fi

    for arg in "$wp_tested" "$wc_tested"; do
        if [ -n "$arg" ] && ! validate_tested_version "$arg"; then
            print_error "Invalid tested-up-to version: $arg"
            echo "Use: X.Y or X.Y.Z"
            exit 1
        fi
    done

    print_msg "$CYAN" "╔════════════════════════════════════════════╗"
    print_msg "$CYAN" "║       YayCheckout Release Packager          ║"
    print_msg "$CYAN" "╚════════════════════════════════════════════╝"
    echo ""

    local version
    if [ "$no_prompt" = true ]; then
        version=${requested_version:-$(get_version)}
        [ -n "$requested_version" ] && update_version "$requested_version"
    else
        prompt_release_version "$requested_version"
        version=$(get_version)
    fi

    # A flag sets its value; without one, only an interactive run (no version, no --no-prompt) asks.
    local interactive=false
    [ "$no_prompt" = false ] && [ -z "$requested_version" ] && interactive=true
    if [ -n "$wp_tested" ]; then
        update_wp_tested "$wp_tested"
    elif [ "$interactive" = true ]; then
        prompt_tested_version "WordPress" "$(get_wp_tested)" update_wp_tested
    fi
    if [ -n "$wc_tested" ]; then
        update_wc_tested "$wc_tested"
    elif [ "$interactive" = true ]; then
        prompt_tested_version "WooCommerce" "$(get_wc_tested)" update_wc_tested
    fi

    local zip_name="${plan_slug}-${version}.zip"

    echo ""
    print_info "Plan: $plan"
    print_info "Version: $version"
    print_info "WP tested up to: $(get_wp_tested)"
    print_info "WC tested up to: $(get_wc_tested)"
    print_info "Output: release/$zip_name"
    echo ""

    update_shell prerelease

    # Regenerate autoload without dev deps for a consistent release
    cd "$PLUGIN_DIR"
    composer dump-autoload --no-dev --classmap-authoritative --quiet

    # vite.config.ts reads this; without it a build falls back to free.
    export YAYCHECKOUT_PLAN="$plan"

    cmd_build

    local temp_dir="$RELEASE_DIR/$plan_slug"
    rm -rf "$temp_dir"
    mkdir -p "$temp_dir"

    print_step "Copying plugin files..."
    cp "$MAIN_FILE" "$temp_dir/"
    cp "$PLUGIN_DIR/index.php" "$temp_dir/"
    cp "$PLUGIN_DIR/YayCheckoutPluginAdapter.php" "$temp_dir/"
    cp "$PLUGIN_DIR/readme.txt" "$temp_dir/"
    cp "$PLUGIN_DIR/uninstall.php" "$temp_dir/"

    cp -r "$PLUGIN_DIR/assets" "$temp_dir/"
    cp -r "$PLUGIN_DIR/includes" "$temp_dir/"
    cp -r "$PLUGIN_DIR/languages" "$temp_dir/"
    cp -r "$PLUGIN_DIR/templates" "$temp_dir/"
    cp -r "$PLUGIN_DIR/vendor-prefixed" "$temp_dir/"

    # Composer autoload only (no packages needed at runtime)
    mkdir -p "$temp_dir/vendor"
    cp "$PLUGIN_DIR/vendor/autoload.php" "$temp_dir/vendor/"
    cp -r "$PLUGIN_DIR/vendor/composer" "$temp_dir/vendor/"

    # includes/Pro/ is the only Pro PHP and nothing else names it (scripts/check-pro-boundary.sh),
    # so removing it leaves a working Free build. The Pro blocks (built from packages/blocks/src/pro/)
    # and the templates made from them go with it: Free registers neither. Only the templates'
    # preview.html stays, a static page Free's picker shows behind a Pro badge.
    if [ "$plan" = "free" ]; then
        local pro_path
        for pro_path in $PRO_PATHS; do
            [ -d "${temp_dir:?}/$pro_path" ] || continue
            find "${temp_dir:?}/$pro_path" -type f ! -name preview.html -delete
            find "${temp_dir:?}/$pro_path" -depth -type d -empty -delete
        done
        print_info "Free build: removed $PRO_PATHS (kept preview.html)"
    fi

    # Distinguish the editions in wp-admin.
    if [ "$plan" = "pro" ]; then
        sed_inplace "s/^ \* Plugin Name: .*/ \* Plugin Name:       $plan_label - WooCommerce Checkout Builder/" "$temp_dir/$PLUGIN_SLUG.php"
    fi

    # The source defaults to dev mode (Vite on :3040); a customer zip must load assets/dist.
    sed_inplace "s/define( 'YAYCHECKOUT_IS_DEVELOPMENT', true )/define( 'YAYCHECKOUT_IS_DEVELOPMENT', false )/" "$temp_dir/$PLUGIN_SLUG.php"

    rm -f "$temp_dir/includes/Engine/Register/RegisterDev.php"
    rm -rf "$temp_dir/assets/dist/admin/.vite" 2>/dev/null || true
    find "$temp_dir" -name ".DS_Store" -delete 2>/dev/null || true
    find "$temp_dir" -name "*.map" -delete 2>/dev/null || true
    find "$temp_dir" -name ".gitkeep" -delete 2>/dev/null || true

    print_step "Creating ZIP archive..."
    cd "$RELEASE_DIR"
    rm -f "$zip_name"
    zip -r -q "$zip_name" "$plan_slug"
    rm -rf "$temp_dir"

    # Check the zip customers receive: a Pro leak is invisible in a dev tree,
    # which always contains every file.
    print_step "Verifying artifact..."
    local pro_entries pro_pattern path
    pro_pattern="${PRO_PATHS// /|}"
    pro_entries=$(unzip -l "$zip_name" | grep -E "$pro_pattern" | grep -cvE '/$|/preview\.html$' || true)

    if [ "$plan" = "free" ] && [ "$pro_entries" -ne 0 ]; then
        print_error "Free artifact contains pro code:"
        unzip -l "$zip_name" | grep -E "$pro_pattern" | grep -vE '/$|/preview\.html$'
        exit 1
    fi

    if [ "$plan" = "pro" ]; then
        for path in $PRO_PATHS; do
            if ! unzip -l "$zip_name" | grep -q "$plan_slug/$path"; then
                print_error "Pro artifact is missing $path"
                exit 1
            fi
        done
    fi

    if ! unzip -p "$zip_name" "$plan_slug/$PLUGIN_SLUG.php" | grep -q "define( 'YAYCHECKOUT_IS_DEVELOPMENT', false )"; then
        print_error "Artifact does not set YAYCHECKOUT_IS_DEVELOPMENT to false"
        exit 1
    fi

    print_success "Artifact matches plan '$plan' ($pro_entries pro file(s))"

    # Restore dev autoload maps so local development still works
    cd "$PLUGIN_DIR"
    composer dump-autoload --classmap-authoritative --quiet

    local size
    size=$(du -h "$RELEASE_DIR/$zip_name" | cut -f1)

    echo ""
    print_success "Release package created!"
    print_info "Package: $RELEASE_DIR/$zip_name"
    print_info "Size: $size"
}

cmd_bump() {
    local new_version=$1
    if [ -z "$new_version" ]; then
        print_error "Usage: ./scripts/run.sh bump <version>"
        exit 1
    fi
    if ! validate_version "$new_version"; then
        print_error "Invalid version format. Use: X.Y.Z, X.Y.Z.W or X.Y.Z-tag"
        exit 1
    fi
    update_version "$new_version"
}

cmd_info() {
    print_info "Plugin: $PLUGIN_SLUG"
    print_info "Version: $(get_version)"
    print_info "WP tested up to: $(get_wp_tested)"
    print_info "WC tested up to: $(get_wc_tested)"
    print_info "Plugin dir: $PLUGIN_DIR"
}

cmd_clean() {
    print_step "Cleaning build artifacts..."

    if [ -d "$PLUGIN_DIR/assets/dist/admin" ]; then
        rm -rf "$PLUGIN_DIR/assets/dist/admin"
        print_success "Removed assets/dist/admin"
    fi

    read -p "Remove node_modules? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]] && [ -d "$ADMIN_DIR/node_modules" ]; then
        rm -rf "$ADMIN_DIR/node_modules"
        print_success "Removed node_modules"
    fi

    print_success "Clean complete"
}

# Captures each template's picker preview from the HTML mock; the script owns the default mock path.
cmd_previews() {
    python3 -I "$PLUGIN_DIR/scripts/capture-template-previews.py" ${1:+--mock "$1"}
}

# Lists docs/backlog/: open items with their unticked boxes, then items whose boxes are all ticked.
cmd_backlog() {
    local dir="$PLUGIN_DIR/docs/backlog"
    local files=()
    local f

    for f in "$dir"/*.md; do
        if [ -e "$f" ]; then
            files+=("$f")
        fi
    done

    if [ ${#files[@]} -eq 0 ]; then
        print_info "Backlog empty"
        return 0
    fi

    local done_list=()
    local summary title ticked total

    for f in "${files[@]}"; do
        # One line "ticked<TAB>total<TAB>title", then the unticked boxes under ## Done when.
        summary=$(awk '
            /^# / && title == "" { title = substr($0, 3) }
            /^## / { in_done = ($0 == "## Done when") ; next }
            in_done && /^- \[[xX]\] / { ticked++; total++ }
            in_done && /^- \[ \] / { total++; open[total] = substr($0, 7) }
            END {
                printf "%d\t%d\t%s\n", ticked, total, title
                for (i = 1; i <= total; i++) if (i in open) print open[i]
            }
        ' "$f")
        ticked=$(printf '%s\n' "$summary" | head -1 | cut -f1)
        total=$(printf '%s\n' "$summary" | head -1 | cut -f2)
        title=$(printf '%s\n' "$summary" | head -1 | cut -f3-)

        if [ "$total" -gt 0 ] && [ "$ticked" -eq "$total" ]; then
            done_list+=("$(basename "$f")  $title")
            continue
        fi

        print_msg "$BLUE" "$(basename "$f")  [$ticked/$total]"
        echo "  $title"
        printf '%s\n' "$summary" | tail -n +2 | while IFS= read -r line; do
            if [ -n "$line" ]; then
                echo "    - [ ] $line"
            fi
        done
    done

    for f in "${done_list[@]+"${done_list[@]}"}"; do
        print_success "$f  (done, remove)"
    done
}

cmd_help() {
    echo "YayCheckout Dev & Release Script"
    echo ""
    echo "Usage: ./scripts/run.sh [command]"
    echo ""
    echo "Commands:"
    echo "  dev                          Update admin shell + Vite dev server (:3040) + blocks watcher"
    echo "  build                        Build admin dashboard + blocks for production"
    echo "  release [version] [--plan=free|pro] [--wp-tested=X.Y] [--wc-tested=X.Y.Z] [--no-prompt]"
    echo "                               Create release ZIP in release/ (default --plan=free)"
    echo "                               Prompts for version and WP/WC tested-up-to unless given"
    echo "  bump <version>               Bump version in plugin files"
    echo "  info                         Show current version info"
    echo "  clean                        Remove admin build artifacts (blocks output is left)"
    echo "  backlog                      List docs/backlog/ items and their open boxes"
    echo "  previews [mock-path]         Capture templates/layouts/{,pro/}*/preview.html from the HTML mock"
    echo "  help                         Show this help message"
    echo ""
    echo "Examples:"
    echo "  ./scripts/run.sh dev                    # Dev server with HMR"
    echo "  ./scripts/run.sh release                # Free package (prompts version)"
    echo "  ./scripts/run.sh release --plan=pro      # Pro package"
    echo "  ./scripts/run.sh release 1.0.1          # Bump to 1.0.1 and package"
    echo "  ./scripts/run.sh release --no-prompt    # Package current version"
    echo "  ./scripts/run.sh release 1.0.1 --wp-tested=6.9 --wc-tested=10.8.0"
}

# ─── Main ───────────────────────────────────────────────────────────────

main() {
    local cmd=${1:-help}
    shift || true

    case $cmd in
        dev)     cmd_dev "$@" ;;
        build)   cmd_build "$@" ;;
        release) cmd_release "$@" ;;
        bump)    cmd_bump "$@" ;;
        info)    cmd_info "$@" ;;
        clean)   cmd_clean "$@" ;;
        backlog) cmd_backlog "$@" ;;
        previews) cmd_previews "$@" ;;
        help|*)  cmd_help ;;
    esac
}

main "$@"
