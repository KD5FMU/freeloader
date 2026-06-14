cd /etc/asterisk/local

sudo tee freeloader.sh > /dev/null << 'EOF'
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

# STEP 1. Update package list
echo "Step 1: Updating package list..."
apt-get update -qq

# STEP 2. Install git if needed
echo "Step 2: Installing git if needed..."
apt-get install -y git

# STEP 3. Get latest files from GitHub
echo "Step 3: Getting latest files from n5ad/freeloader..."
if [ -d "/tmp/freeloader" ]; then
    echo "Updating existing repository..."
    cd /tmp/freeloader && git pull
else
    echo "Cloning repository..."
    git clone https://github.com/n5ad/freeloader.git /tmp/freeloader
fi

# STEP 4. Create /my_uploads directory + permissions
echo "Step 4: Creating /my_uploads directory"
sudo mkdir -p /my_uploads
UPLOAD_USER="${SUDO_USER:-$(whoami)}"
echo "Granting /my_uploads access to user: $UPLOAD_USER"

if id -nG "$UPLOAD_USER" | grep -qw "www-data"; then
    echo "$UPLOAD_USER is already in www-data group"
else
    echo "Adding $UPLOAD_USER to www-data group"
    sudo usermod -aG www-data "$UPLOAD_USER"
fi

sudo chown -R www-data:www-data /my_uploads
sudo chmod -R 2775 /my_uploads
echo "✅ /my_uploads directory ready"

# STEP 5. Create freeloader subdirectory
echo "Step 5: Creating freeloader subdirectory"
sudo mkdir -p /var/www/html/supermon/custom/freeloader
sudo chown -R www-data:www-data /var/www/html/supermon/custom/freeloader

# STEP 6. Copy freeloader.inc
echo "Step 6: Installing freeloader.inc"
sudo cp /tmp/freeloader/freeloader.inc /var/www/html/supermon/custom/
sudo chown www-data:www-data /var/www/html/supermon/custom/freeloader.inc
sudo chmod 644 /var/www/html/supermon/custom/freeloader.inc
echo "✅ freeloader.inc installed"

# STEP 7. Copy PHP files
echo "Step 7: Installing backend PHP files"
sudo cp /tmp/freeloader/freeloader_upload.php /var/www/html/supermon/custom/freeloader/
sudo cp /tmp/freeloader/freeloader_delete.php /var/www/html/supermon/custom/freeloader/

sudo chown www-data:www-data /var/www/html/supermon/custom/freeloader/*.php
sudo chmod 644 /var/www/html/supermon/custom/freeloader/*.php
echo "✅ Backend files installed"

# STEP 8. Smart patch footer.inc
echo "Step 8: Adding Freeloader to footer.inc"
FOOTER_INC="/var/www/html/supermon/footer.inc"
BACKUP_SUFFIX=".bak.$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$FOOTER_INC" ]; then
    echo " → footer.inc not found → skipping"
else
    BACKUP_FOOTER="${FOOTER_INC}${BACKUP_SUFFIX}"
    cp -v "$FOOTER_INC" "$BACKUP_FOOTER"
    echo "Backup of footer.inc created: $BACKUP_FOOTER"

    if grep -q 'freeloader\.inc' "$FOOTER_INC"; then
        echo "Freeloader include already present → skipping"
    else
        echo "Patching footer.inc to add Freeloader..."
        awk '
        # Look for the start of the logged-in block
        /if \(\$_SESSION\['"'"'sm61loggedin'"'"'\] === true\) \{/ {
            print
            inblock = 1
            next
        }
        # If announcement.inc is present, add after it
        inblock && /include.*announcement\.inc/ {
            print
            print "<?php include(\"custom/freeloader.inc\"); ?>"
            inblock = 0
            next
        }
        # If no announcement.inc, add right after the closing ?> of the session block
        inblock && /^\s*\?>\s*$/ {
            print
            print "<?php include(\"custom/freeloader.inc\"); ?>"
            inblock = 0
            next
        }
        { print }
        ' "$FOOTER_INC" > "$FOOTER_INC.tmp" && mv "$FOOTER_INC.tmp" "$FOOTER_INC"
        echo "✅ footer.inc patched successfully (Freeloader include added)."
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
echo "2. Test uploading a file"
echo ""
echo "Installer location: /etc/asterisk/local/freeloader.sh"
echo "=================================================="
EOF

sudo chmod +x freeloader.sh
echo "✅ Updated freeloader.sh installer created in /etc/asterisk/local"
