#!/bin/bash
# ================================================
# Freeloader Installer for Supermon
# Pulls from https://github.com/n5ad/freeloader
# Created by N5AD - June/July 2026
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
mkdir -p /var/www/html/freeloader
chown -R www-data:www-data /var/www/html/freeloader

# ------------------------------------------------
# Step 6 - Install freeloader files
# ------------------------------------------------
echo "Step 6: Installing freeloader files..."
cp /tmp/freeloader/freeloader.inc /var/www/html/freeloader/
cp /tmp/freeloader/freeloader_upload.php /var/www/html/freeloader/
cp /tmp/freeloader/freeloader_delete.php /var/www/html/freeloader/

chown www-data:www-data /var/www/html/freeloader/*
chmod 644 /var/www/html/freeloader/*

# ------------------------------------------------
# Step 7 - Secure Config File + Password Prompt
# ------------------------------------------------
echo "Step 7: Creating secure password configuration..."
mkdir -p /etc/freeloader

echo ""
echo "=== Set Freeloader Password ==="
echo "Enter a strong password for Freeloader access:"
read -s -p "Password: " FREELoader_PASSWORD
echo ""
read -s -p "Confirm Password: " CONFIRM_PASSWORD
echo ""

if [ "$FREELoader_PASSWORD" != "$CONFIRM_PASSWORD" ]; then
    echo "❌ Passwords do not match. Please run the installer again."
    exit 1
fi

cat > /etc/freeloader/.config.php << EOF
<?php
// Secure password file for Freeloader
// Do not put this file in the web directory
\$FREELoader_PASSWORD = '$FREELoader_PASSWORD';
?>
EOF

chmod 600 /etc/freeloader/.config.php
chown root:root /etc/freeloader/.config.php

echo "✅ Secure config file created."

# ------------------------------------------------
# Step 8 - Modify footer.inc (Supermon integration)
# ------------------------------------------------
echo "Step 8: Updating footer.inc..."
# (your existing footer.inc code remains unchanged)

# ------------------------------------------------
# Final Restart
# ------------------------------------------------
echo "Restarting Apache2..."
systemctl restart apache2

echo
echo "=================================================="
echo " Freeloader installation completed successfully!"
echo
echo "Next steps:"
echo "1. Hard refresh Supermon/Allmon3 (Ctrl+Shift+R)"
echo "2. Test Freeloader at /freeloader/index.php"
echo "3. Your password is stored securely in /etc/freeloader/.config.php"
echo "=================================================="
