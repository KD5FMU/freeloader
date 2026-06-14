
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
echo "🚀 Starting Freeloader Installer"
echo "=================================================="

# STEP 1-3: Update + Git + Clone
echo "Step 1: Updating package list..."
apt-get update -qq

echo "Step 2: Installing git if needed..."
apt-get install -y git

echo "Step 3: Getting latest files from n5ad/freeloader..."
if [ -d "/tmp/freeloader/.git" ]; then
    cd /tmp/freeloader
    git pull
else
    rm -rf /tmp/freeloader
    git clone https://github.com/n5ad/freeloader.git /tmp/freeloader
fi

# STEP 4: /my_uploads
echo "Step 4: Creating /my_uploads directory..."

mkdir -p /my_uploads

UPLOAD_USER="${SUDO_USER:-$(whoami)}"

if ! id -nG "$UPLOAD_USER" | grep -qw "www-data"; then
    usermod -aG www-data "$UPLOAD_USER"
fi

chown -R www-data:www-data /my_uploads
chmod -R 2775 /my_uploads

echo "✅ /my_uploads ready"

# STEP 5: Create freeloader directory
echo "Step 5: Creating freeloader subdirectory..."

mkdir -p /var/www/html/supermon/custom/freeloader
chown -R www-data:www-data /var/www/html/supermon/custom/freeloader

# STEP 6: Install freeloader.inc
echo "Step 6: Installing freeloader.inc..."

cp /tmp/freeloader/freeloader.inc /var/www/html/supermon/custom/
chown www-data:www-data /var/www/html/supermon/custom/freeloader.inc
chmod 644 /var/www/html/supermon/custom/freeloader.inc

# STEP 7: Install backend PHP files
echo "Step 7: Installing PHP backend files..."

cp /tmp/freeloader/freeloader_upload.php /var/www/html/supermon/custom/freeloader/
cp /tmp/freeloader/freeloader_delete.php /var/www/html/supermon/custom/freeloader/

chown www-data:www-data /var/www/html/supermon/custom/freeloader/*.php
chmod 644 /var/www/html/supermon/custom/freeloader/*.php

```bash
# STEP 8: Insert include into footer.inc
echo "Step 8: Updating footer.inc..."

FOOTER_FILE="/var/www/html/supermon/footer.inc"
BACKUP_SUFFIX=".bak.$(date +%Y%m%d_%H%M%S)"

if [ ! -f "$FOOTER_FILE" ]; then
    echo "ERROR: $FOOTER_FILE not found!"
    exit 1
fi

if grep -qF '<?php include_once "custom/freeloader.inc"; ?>' "$FOOTER_FILE"; then
    echo "✅ freeloader.inc include already exists. Skipping."
else
    BACKUP_FILE="${FOOTER_FILE}${BACKUP_SUFFIX}"
    cp "$FOOTER_FILE" "$BACKUP_FILE"

    awk '
    BEGIN { inserted=0 }

    /^[[:space:]]*<SCRIPT>/ && inserted==0 {
        print "<?php include_once \"custom/freeloader.inc\"; ?>"
        inserted=1
    }

    { print }
    ' "$BACKUP_FILE" > "$FOOTER_FILE"

    chmod 644 "$FOOTER_FILE"

    echo "✅ Inserted freeloader.inc include into footer.inc"
fi
```

echo
echo "=================================================="
echo "✅ Freeloader installation completed successfully!"
echo
echo "Next steps:"
echo "  1. Hard refresh Supermon (Ctrl+Shift+R)"
echo "  2. Confirm Freeloader appears before the JavaScript section"
echo "  3. Test uploading a file"
echo
echo "Installer completed successfully."
echo "=================================================="

