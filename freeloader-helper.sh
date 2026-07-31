#!/bin/bash
# freeloader-helper.sh
# Restricted privileged helper for Freeloader
# N5AD / hardened - July 2026

set -euo pipefail

ALLOWED_DIRS=(
    "/my_uploads"
    "/etc/asterisk"
    "/etc/asterisk/local"
    "/etc/allmon3"
    "/var/lib/asterisk"
    "/var/www/html/supermon"
    "/usr/share/allmon3"
)

is_allowed_path() {
    local target="$1"
    local resolved
    resolved=$(realpath -e "$target" 2>/dev/null) || return 1

    local dir
    for dir in "${ALLOWED_DIRS[@]}"; do
        local basedir
        basedir=$(realpath -e "$dir" 2>/dev/null) || continue
        if [[ "$resolved" == "$basedir" || "$resolved" == "$basedir"/* ]]; then
            return 0
        fi
    done
    return 1
}

die() {
    echo "ERROR: $*" >&2
    exit 1
}

cmd="${1:-}"

case "$cmd" in
    cat)
        [[ $# -eq 2 ]] || die "cat requires exactly one path"
        target="$2"
        is_allowed_path "$target" || die "Path not allowed: $target"
        [[ -f "$target" ]] || die "Not a regular file: $target"
        cat -- "$target"
        ;;

    cp)
        [[ $# -eq 3 ]] || die "cp requires src and dst"
        src="$2"
        dst="$3"
        [[ -f "$src" ]] || die "Source not found or not a file: $src"
        dstdir=$(dirname -- "$dst")
        is_allowed_path "$dstdir" || die "Destination directory not allowed: $dstdir"
        real_dst_dir=$(realpath -e "$dstdir")
        base_name=$(basename -- "$dst")
        [[ "$base_name" != *..* && "$base_name" != */* ]] || die "Invalid destination filename"
        cp -- "$src" "$real_dst_dir/$base_name"
        chmod 644 "$real_dst_dir/$base_name" 2>/dev/null || true
        ;;

    rm)
        [[ $# -eq 2 ]] || die "rm requires exactly one path"
        target="$2"
        is_allowed_path "$target" || die "Path not allowed: $target"
        [[ -f "$target" ]] || die "Not a regular file (or does not exist): $target"
        rm -f -- "$target"
        ;;

    ls)
        # Usage: freeloader-helper ls <absolute-dir>
        # Prints: filename<TAB>size_bytes<TAB>mtime_epoch  (regular files, no dotfiles)
        [[ $# -eq 2 ]] || die "ls requires exactly one directory"
        target="$2"
        is_allowed_path "$target" || die "Path not allowed: $target"
        [[ -d "$target" ]] || die "Not a directory: $target"
        while IFS= read -r -d '' f; do
            base=$(basename -- "$f")
            [[ "$base" == .* ]] && continue
            [[ -f "$f" ]] || continue
            sz=$(stat -c '%s' -- "$f" 2>/dev/null || echo 0)
            mt=$(stat -c '%Y' -- "$f" 2>/dev/null || echo 0)
            printf '%s\t%s\t%s\n' "$base" "$sz" "$mt"
        done < <(find "$target" -maxdepth 1 -type f -print0 2>/dev/null)
        ;;

    restart_asterisk)
        [[ $# -eq 1 ]] || die "restart_asterisk takes no arguments"
        if [ -x /bin/systemctl ]; then
            SYSTEMCTL=/bin/systemctl
        elif [ -x /usr/bin/systemctl ]; then
            SYSTEMCTL=/usr/bin/systemctl
        else
            die "systemctl not found"
        fi
        "$SYSTEMCTL" restart asterisk
        ;;

    *)
        die "Unknown or missing command. Allowed: cat, cp, rm, ls, restart_asterisk"
        ;;
esac

exit 0
