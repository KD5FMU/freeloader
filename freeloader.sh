#!/bin/bash
# ================================================
# Freeloader Installer for Supermon
# Pulls from https://github.com/n5ad/freeloader
# Created by N5AD - June 2026
# ================================================

set -e

# Must be run as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this installer with:"
    echo "sudo ./freeloader.sh"
    exit 1
fi

echo "=================================================="
echo " Starting Freeloader Installer"
echo "=================================================="

# ------------------------------------------------
# Step 1 - Update package list
# ------------------------------------------------
echo "Step 1: Updating package list..."
apt-get update -qq

# ------------------------------------------------
# Step 2 - Install git
# ------------------------------------------------
echo "Step 2: Installing git if needed..."
apt-get install -y git

# ------------------------------------------------
# Step 3 - Clone or update repository
# ------------------------------------------------
echo "Step 3: Getting latest files from n5ad/freeloader..."

if [ -d "/tmp/freeloader/.git" ]; then
    cd /tmp/freeloader
    git pull
else
    rm -rf /tmp/freeloader
    git clone https://github.com/n5ad/freeloader.git /tmp/freeloader
fi

# ------------------------------------------------
# Step 4 - Create upload directory
# ------------------------------------------------
echo "Step 4: Creating /my_uploads directory..."

mkdir -p /my_uploads

UPLOAD_USER="${SUDO_USER:-$(whoami)}"

if ! id -nG "$UPLOAD_USER" | grep -qw "www-data"; then
    usermod -aG www-data "$UPLOAD_USER"
fi

chown -R www-data:www-data /my_uploads
chmod -R 2775 /my_uploads

echo " /my_uploads ready"

# ------------------------------------------------
# Step 5 - Create freeloader directory
# ------------------------------------------------
echo "Step 5: Creating freeloader directory..."

mkdir -p /var/www/html/supermon/custom/freeloader

chown -R www-data:www-data /var/www/html/supermon/custom/freeloader

# ------------------------------------------------
# Step 6 - Install freeloader.inc
# ------------------------------------------------
echo "Step 6: Installing freeloader.inc..."

cp /tmp/freeloader/freeloader.inc /var/www/html/supermon/custom/

chown www-data:www-data /var/www/html/supermon/custom/freeloader.inc

chmod 644 /var/www/html/supermon/custom/freeloader.inc

# ------------------------------------------------
# Step 7 - Install PHP backend files
# ------------------------------------------------
echo "Step 7: Installing backend PHP files..."

cp /tmp/freeloader/freeloader_upload.php /var/www/html/supermon/custom/freeloader/

cp /tmp/freeloader/freeloader_delete.php /var/www/html/supermon/custom/freeloader/

chown www-data:www-data /var/www/html/supermon/custom/freeloader/*.php

chmod 644 /var/www/html/supermon/custom/freeloader/*.php

# ------------------------------------------------
# Step 8 - Modify footer.inc (idempotent)
# ------------------------------------------------
echo "Step 8: Updating footer.inc..."

FOOTER_FILE="/var/www/html/supermon/footer.inc"
BACKUP_FILE="${FOOTER_FILE}.bak.$(date +%Y%m%d_%H%M%S)"

if [ ! -f "$FOOTER_FILE" ]; then
    echo
    echo "ERROR: footer.inc not found:"
    echo "$FOOTER_FILE"
    exit 1
fi

if grep -qF '<?php include_once "custom/freeloader.inc"; ?>' "$FOOTER_FILE"; then

    echo " freeloader.inc already present."

else

    cp "$FOOTER_FILE" "$BACKUP_FILE"

    echo "Backup created:"
    echo "  $BACKUP_FILE"

    awk '
    BEGIN {
        inserted = 0
    }

    /^[[:space:]]*<SCRIPT>/ && inserted == 0 {
        print "<?php include_once \"custom/freeloader.inc\"; ?> <br><br>"
        inserted = 1
    }

    {
        print
    }
    ' "$BACKUP_FILE" > "${FOOTER_FILE}.tmp"

    mv "${FOOTER_FILE}.tmp" "$FOOTER_FILE"

    chmod 644 "$FOOTER_FILE"

    echo " freeloader.inc inserted successfully."

fi

# ------------------------------------------------
# Finished
# ------------------------------------------------
echo
echo "=================================================="
echo " Freeloader installation completed successfully!"
echo
echo "Please:"
echo "  1. Hard refresh Supermon (Ctrl+Shift+R)"
echo "  2. Verify freeloader appears before the first"
echo "     <SCRIPT> tag in footer.inc"
echo "  3. Test uploading a file"
echo
echo "=================================================="

