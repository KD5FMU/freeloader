#!/bin/bash

# ================================================================

# Freeloader Installer for ASL3 / Supermon / Allmon3 (HARDENED)

# ================================================================

# Pulls ALL application files exclusively from:

#   https://github.com/n5ad/freeloader

#

# Features installed:

#   - Upload / Download / Delete

#   - Edit + Save + Save As (pattern-based editable files)

#   - Restart Asterisk

#   - Directory whitelist

#   - Restricted sudo helper (no blanket cp/rm/systemctl)

#   - Bcrypt password hash

#   - Login rate limiting

#   - CSRF tokens

#   - Session regenerate on login

#   - Apache protection for /my_uploads

#

# Usage:

#   sudo bash freeloader.sh

#

# N5AD - July 2026

# ================================================================

set -euo pipefail


# ----------------------------------------------------------------

# Must be root

# ----------------------------------------------------------------

if [ "${EUID:-$(id -u)}" -ne 0 ]; then

    echo "Please run this installer as root:"

    echo "  sudo bash freeloader.sh"

    exit 1

fi


REPO_URL="https://github.com/n5ad/freeloader.git"

REPO_DIR="/tmp/freeloader"

WEB_DIR="/var/www/html/freeloader"

HELPER_PATH="/usr/local/bin/freeloader-helper"

CONFIG_DIR="/etc/freeloader"

STATE_DIR="/var/lib/freeloader"

UPLOAD_DIR="/my_uploads"


REQUIRED_FILES=(

    freeloader.inc

    freeloader_common.php

    freeloader_upload.php

    freeloader_delete.php

    freeloader_download.php

    index.php

    freeloader-helper.sh

)


echo "=================================================="

echo " Freeloader Installer (Hardened)"

echo " Source: ${REPO_URL}"

echo "=================================================="

echo


# ----------------------------------------------------------------

# Step 1 - Packages

# ----------------------------------------------------------------

echo "Step 1: Updating package list..."

apt-get update -qq


echo "Step 2: Installing required packages..."

apt-get install -y git php-cli apache2 2>/dev/null || apt-get install -y git php-cli


# ----------------------------------------------------------------

# Step 3 - Clone / update repository (ONLY source of files)

# ----------------------------------------------------------------

echo "Step 3: Fetching latest files from GitHub..."

if [ -d "${REPO_DIR}/.git" ]; then

    cd "${REPO_DIR}"

    git fetch --all --prune

    # Prefer main, fall back to master

    if git show-ref --verify --quiet refs/remotes/origin/main; then

        git checkout -B main origin/main

        git reset --hard origin/main

    elif git show-ref --verify --quiet refs/remotes/origin/master; then

        git checkout -B master origin/master

        git reset --hard origin/master

    else

        git pull --ff-only || git reset --hard ORIG_HEAD

    fi

else

    rm -rf "${REPO_DIR}"

    git clone "${REPO_URL}" "${REPO_DIR}"

fi

cd "${REPO_DIR}"

echo "  Repository ready at ${REPO_DIR}"

echo "  Commit: $(git rev-parse --short HEAD 2>/dev/null || echo unknown)"


# Verify required files exist in the repo

MISSING=0

for f in "${REQUIRED_FILES[@]}"; do

    if [ ! -f "${REPO_DIR}/${f}" ]; then

        echo "  ERROR: Required file missing from repository: ${f}"

        MISSING=1

    fi

done

if [ "${MISSING}" -ne 0 ]; then

    echo

    echo "The GitHub repository is missing required hardened files."

    echo "Push the complete Freeloader package to:"

    echo "  ${REPO_URL}"

    echo "then re-run this installer."

    exit 1

fi

echo "  All required files present."


# ----------------------------------------------------------------

# Step 4 - Directories & permissions

# ----------------------------------------------------------------

echo "Step 4: Creating directories..."

mkdir -p "${UPLOAD_DIR}"

mkdir -p "${WEB_DIR}"

mkdir -p "${CONFIG_DIR}"

mkdir -p "${STATE_DIR}"

mkdir -p /usr/local/bin


UPLOAD_USER="${SUDO_USER:-$(logname 2>/dev/null || whoami)}"

if id "${UPLOAD_USER}" &>/dev/null; then

    if ! id -nG "${UPLOAD_USER}" | grep -qw www-data; then

        usermod -aG www-data "${UPLOAD_USER}" || true

    fi

fi


chown -R www-data:www-data "${UPLOAD_DIR}"

chmod -R 2775 "${UPLOAD_DIR}"

chown www-data:www-data "${STATE_DIR}"

chmod 750 "${STATE_DIR}"

# Remove any old .htaccess inside uploads (protection is via Apache conf)

rm -f "${UPLOAD_DIR}/.htaccess"


# ----------------------------------------------------------------

# Step 5 - Restricted helper (ONLY privileged binary)

# ----------------------------------------------------------------

echo "Step 5: Installing restricted helper..."

# Ensure destination is a FILE, not a leftover directory from older installs

if [ -d "${HELPER_PATH}" ]; then

    echo "  Removing leftover directory at ${HELPER_PATH}..."

    rm -rf "${HELPER_PATH}"

fi

cp "${REPO_DIR}/freeloader-helper.sh" "${HELPER_PATH}"

chmod 755 "${HELPER_PATH}"

chown root:root "${HELPER_PATH}"

echo "  Installed ${HELPER_PATH}"


# ----------------------------------------------------------------

# Step 6 - Web application files

# ----------------------------------------------------------------

echo "Step 6: Installing web application files..."

for f in freeloader.inc freeloader_common.php freeloader_upload.php \

         freeloader_delete.php freeloader_download.php index.php; do

    cp "${REPO_DIR}/${f}" "${WEB_DIR}/"

    echo "  + ${f}"

done

chown -R www-data:www-data "${WEB_DIR}"

find "${WEB_DIR}" -type f -exec chmod 644 {} \;

find "${WEB_DIR}" -type d -exec chmod 755 {} \;


# ----------------------------------------------------------------

# Step 7 - Password (bcrypt hash)

# ----------------------------------------------------------------

echo "Step 7: Setting Freeloader password (stored as bcrypt hash)..."

echo

echo "=== Set Freeloader Password ==="

echo "Enter a strong password for Freeloader access:"

read -r -s -p "Password: " PLAIN_PASSWORD

echo

read -r -s -p "Confirm Password: " CONFIRM_PASSWORD

echo

if [ "${PLAIN_PASSWORD}" != "${CONFIRM_PASSWORD}" ]; then

    echo "ERROR: Passwords do not match. Aborting."

    exit 1

fi

if [ -z "${PLAIN_PASSWORD}" ]; then

    echo "ERROR: Password cannot be empty."

    exit 1

fi


if ! command -v php >/dev/null 2>&1; then

    echo "ERROR: php-cli is required to generate the password hash."

    exit 1

fi


HASH="$(PW="${PLAIN_PASSWORD}" php -r 'echo password_hash(getenv("PW"), PASSWORD_DEFAULT);')"

if [ -z "${HASH}" ] || [ "${HASH}" = "false" ]; then

    echo "ERROR: Failed to generate password hash."

    exit 1

fi

# Clear password from environment as soon as possible

unset PLAIN_PASSWORD CONFIRM_PASSWORD


cat > "${CONFIG_DIR}/.config.php" << EOF

<?php

// Secure configuration for Freeloader (hardened)

// Do NOT place this file under the web root

\$FREELoader_PASSWORD_HASH = '${HASH}';

?>

EOF

chmod 640 "${CONFIG_DIR}/.config.php"

chown root:www-data "${CONFIG_DIR}/.config.php"

echo "  Password hash written to ${CONFIG_DIR}/.config.php"


# ----------------------------------------------------------------

# Step 8 - Sudoers (helper ONLY)

# ----------------------------------------------------------------

echo "Step 8: Installing restricted sudoers rule..."

cat > /etc/sudoers.d/99-freeloader << EOF

# Freeloader - www-data may run ONLY the restricted helper as root

www-data ALL=(root) NOPASSWD: ${HELPER_PATH}

EOF

chmod 0440 /etc/sudoers.d/99-freeloader

if ! visudo -cf /etc/sudoers.d/99-freeloader; then

    echo "ERROR: sudoers validation failed. Removing rule."

    rm -f /etc/sudoers.d/99-freeloader

    exit 1

fi

echo "  Sudoers rule installed and validated."


# ----------------------------------------------------------------

# Step 9 - Apache protection for upload directory

# ----------------------------------------------------------------

echo "Step 9: Configuring Apache protection for ${UPLOAD_DIR}..."

cat > /etc/apache2/conf-available/freeloader-uploads.conf << EOF

# Freeloader: deny web access / script execution for the upload directory

<Directory "${UPLOAD_DIR}">

    Options -ExecCGI -Indexes

    AllowOverride None

    Require all denied

    <FilesMatch "\\.(php|phtml|php[0-9]|phar)\$">

        Require all denied

    </FilesMatch>

</Directory>

EOF

a2enconf freeloader-uploads >/dev/null 2>&1 || true

echo "  Installed /etc/apache2/conf-available/freeloader-uploads.conf"


# ----------------------------------------------------------------

# Step 10 - Restart Apache

# ----------------------------------------------------------------

echo "Step 10: Restarting Apache..."

if systemctl restart apache2 2>/dev/null; then

    echo "  Apache restarted."

elif service apache2 restart 2>/dev/null; then

    echo "  Apache restarted (service)."

else

    echo "  WARNING: Could not restart Apache automatically. Restart it manually."

fi


# ----------------------------------------------------------------

# Done

# ----------------------------------------------------------------

echo

echo "=================================================="

echo " Freeloader (Hardened) installation complete!"

echo

echo " Source commit : $(cd "${REPO_DIR}" && git rev-parse --short HEAD 2>/dev/null || echo unknown)"

echo " Web path      : ${WEB_DIR}"

echo " Helper        : ${HELPER_PATH}"

echo " Config        : ${CONFIG_DIR}/.config.php"

echo " Uploads       : ${UPLOAD_DIR}"

echo

echo " Security features:"

echo "  - Files pulled only from GitHub"

echo "  - Directory whitelist (see freeloader-helper + freeloader_common.php)"

echo "  - Privileged ops only via locked-down helper"

echo "  - Password stored as bcrypt hash"

echo "  - Login rate limiting"

echo "  - CSRF tokens on state-changing actions"

echo "  - Session ID regenerated on login"

echo "  - /my_uploads protected via Apache config"

echo

echo " Default allowed directories:"

echo "   /my_uploads"

echo "   /etc/asterisk"

echo "   /etc/allmon3"

echo "   /var/lib/asterisk"

echo "   /var/www/html/supermon"

echo

echo " Access: http://YOUR-NODE/freeloader/"

echo " 73 N5AD"

echo "=================================================="

