#!/bin/bash
# ================================================
# Freeloader Installer for Supermon
# Pulls from https://github.com/n5ad/freeloader
# Created by N5AD - June 2026
# ================================================

set -e

echo "=================================================="
echo "🚀 Starting Freeloader Installer"
echo "=================================================="

# STEP 1-3: Update + Git + Clone
echo "Step 1: Updating package list..."
apt-get update -qq

echo "Step 2: Installing git if needed..."
apt-get install -y git

echo "Step 3: Getting latest files from n5ad/freeloader..."
if [ -d "/tmp/freeloader" ]; then
    cd /tmp/freeloader && git pull
else
    git clone https://github.com/n5ad/freeloader.git /tmp/freeloader
fi

# STEP 4: /my_uploads
echo "Step 4: Creating /my_uploads directory"
sudo mkdir -p /my_uploads
UPLOAD_USER="${SUDO_USER:-$(whoami)}"
if ! id -nG "$UPLOAD_USER" | grep -qw "www-data"; then
    sudo usermod -aG www-data "$UPLOAD_USER"
fi
sudo chown -R www-data:www-data /my_uploads
sudo chmod -R 2775 /my_uploads
echo "✅ /my_uploads ready"

# STEP 5-7: Folders and files
echo "Step 5: Creating freeloader subdirectory"
sudo mkdir -p /var/www/html/supermon/custom/freeloader
sudo chown -R www-data:www-data /var/www/html/supermon/custom/freeloader

echo "Step 6: Installing freeloader.inc"
sudo cp /tmp/freeloader/freeloader.inc /var/www/html/supermon/custom/
sudo chown www-data:www-data /var/www/html/supermon/custom/freeloader.inc
sudo chmod 644 /var/www/html/supermon/custom/freeloader.inc

echo "Step 7: Installing PHP backend files"
sudo cp /tmp/freeloader/freeloader_upload.php /var/www/html/supermon/custom/freeloader/
sudo cp /tmp/freeloader/freeloader_delete.php /var/www/html/supermon/custom/freeloader/
sudo chown www-data:www-data /var/www/html/supermon/custom/freeloader/*.php
sudo chmod 644 /var/www/html/supermon/custom/freeloader/*.php

# ====================== NEW STEP 8 ======================
echo "Step 8: Adding Freeloader include just before <SCRIPT> in footer.inc"
FOOTER_INC="/var/www/html/supermon/footer.inc"
BACKUP_SUFFIX=".bak.$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$FOOTER_INC" ]; then
    echo " → footer.inc not found → skipping"
else
    BACKUP_FOOTER="${FOOTER_INC}${BACKUP_SUFFIX}"
    cp -v "$FOOTER_INC" "$BACKUP_FOOTER"
    echo "Backup created: $BACKUP_FOOTER"

    if grep -q 'freeloader\.inc' "$FOOTER_INC"; then
        echo "Freeloader already present → skipping"
    else
        echo "Patching footer.inc — placing include just before <SCRIPT>..."
        awk '
        # Inside logged-in block
        /if \(\$_SESSION\['"'"'sm61loggedin'"'"'\] === true\) \{/ {
            print
            inblock = 1
            next
        }
        # Insert just before the first <script> or <SCRIPT> tag
        inblock && /<script/i {
            print "<?php include(\"custom/freeloader.inc\"); ?>"
            print
            inblock = 0
            next
        }
        # Fallback: if no script tag found, put before final ?>
        inblock && /^\s*\?>\s*$/ {
            print "<?php include(\"custom/freeloader.inc\"); ?>"
            print
            inblock = 0
            next
        }
        { print }
        ' "$FOOTER_INC" > "$FOOTER_INC.tmp" && mv "$FOOTER_INC.tmp" "$FOOTER_INC"
        echo "✅ footer.inc patched — Freeloader include added just before <SCRIPT>."
    fi

    chown www-data:www-data "$FOOTER_INC" 2>/dev/null || true
    chmod 644 "$FOOTER_INC" 2>/dev/null || true
fi

echo ""
echo "=================================================="
echo "✅ Freeloader installation completed successfully!"
echo ""
echo "Next steps:"
echo "1. Hard refresh Supermon (Ctrl + Shift + R)"
echo "2. Confirm Freeloader appears before the JavaScript section"
echo "3. Test uploading a file"
echo ""
echo "Installer location: /etc/asterisk/local/freeloader.sh"
echo "=================================================="
