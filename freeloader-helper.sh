#!/bin/bash

# freeloader-helper.sh

# Restricted privileged helper for Freeloader

# Only allows specific operations on a hard-coded whitelist of directories.

# N5AD / hardened - July 2026


set -euo pipefail


# ============================================================

# HARD-CODED ALLOWED BASE DIRECTORIES

# Edit this list if you need additional paths.

# Paths must be absolute and will be resolved with realpath.

# ============================================================

ALLOWED_DIRS=(

    "/my_uploads"

    "/etc/asterisk"

    "/var/lib/asterisk"

    "/var/www/html/supermon"

    "/usr/local/bin"

    "/usr/share/allmon3"

    "/etc/allmon3"

    "/var/www/html/freeloader"

    "/etc/asterisk/local"

    




)


# ------------------------------------------------------------

# Helpers

# ------------------------------------------------------------

is_allowed_path() {

    local target="$1"

    local resolved

    resolved=$(realpath -e "$target" 2>/dev/null) || return 1


    local dir

    for dir in "${ALLOWED_DIRS[@]}"; do

        local basedir

        basedir=$(realpath -e "$dir" 2>/dev/null) || continue

        # Check that resolved path is exactly the base or under it

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


# ------------------------------------------------------------

# Command dispatch

# ------------------------------------------------------------

cmd="${1:-}"


case "$cmd" in

    cat)

        # Usage: freeloader-helper cat <absolute-path>

        [[ $# -eq 2 ]] || die "cat requires exactly one path"

        target="$2"

        is_allowed_path "$target" || die "Path not allowed: $target"

        # Only regular files

        [[ -f "$target" ]] || die "Not a regular file: $target"

        cat -- "$target"

        ;;


    cp)

        # Usage: freeloader-helper cp <src> <dst>

        # src is usually a temp file owned by www-data; dst must be under allowed dir

        [[ $# -eq 3 ]] || die "cp requires src and dst"

        src="$2"

        dst="$3"

        # Source must exist and be a regular file

        [[ -f "$src" ]] || die "Source not found or not a file: $src"

        # Destination directory must be allowed

        dstdir=$(dirname -- "$dst")

        is_allowed_path "$dstdir" || die "Destination directory not allowed: $dstdir"

        # Prevent writing outside by resolving

        real_dst_dir=$(realpath -e "$dstdir")

        base_name=$(basename -- "$dst")

        # Final safety: no path components in basename

        [[ "$base_name" != *..* && "$base_name" != */* ]] || die "Invalid destination filename"

        cp -- "$src" "$real_dst_dir/$base_name"

        # Reasonable permissions for config files

        chmod 644 "$real_dst_dir/$base_name" 2>/dev/null || true

        ;;


    rm)

        # Usage: freeloader-helper rm <absolute-path>

        [[ $# -eq 2 ]] || die "rm requires exactly one path"

        target="$2"

        is_allowed_path "$target" || die "Path not allowed: $target"

        [[ -f "$target" ]] || die "Not a regular file (or does not exist): $target"

        rm -f -- "$target"

        ;;


    restart_asterisk)

        # Usage: freeloader-helper restart_asterisk

        [[ $# -eq 1 ]] || die "restart_asterisk takes no arguments"

        systemctl restart asterisk

        ;;


    *)

        die "Unknown or missing command. Allowed: cat, cp, rm, restart_asterisk"

        ;;

esac


exit 0

