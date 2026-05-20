#!/bin/bash
# ==============================================================================
# Enterprise MDM Control Center — Automated VPS Provisioning Script (Ubuntu 22.04 LTS / 24.04 LTS)
# ==============================================================================
# Installs Apache2, PHP 8.2 with dependencies, MySQL, Composer, Firewall Rules,
# creates directory structures, sets permissions, and seeds the initial schema.
# ==============================================================================

set -e

# Configuration variables
REMOTE_PATH="/var/www/html/mdm"
DB_NAME="mdm_db"
DB_USER="mdm_admin"
DB_PASS="mdm_secure_pass_2026"

echo "=============================================================================="
echo "  🚀 Starting MDM VPS Server Setup & Package Installation"
echo "=============================================================================="

# 1. Update system package repository
echo "🔄 Updating apt repositories..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y && apt-get upgrade -y

# 2. Add PHP repository and install PHP 8.2 with modules
echo "📦 Adding ondrej/php repository and installing PHP 8.2..."
apt-get install -y software-properties-common curl git unzip ufw
add-apt-repository ppa:ondrej/php -y
apt-get update -y

apt-get install -y \
  apache2 \
  libapache2-mod-php8.2 \
  php8.2 \
  php8.2-common \
  php8.2-mysql \
  php8.2-xml \
  php8.2-xmlrpc \
  php8.2-curl \
  php8.2-gd \
  php8.2-imagick \
  php8.2-cli \
  php8.2-dev \
  php8.2-imap \
  php8.2-mbstring \
  php8.2-opcache \
  php8.2-soap \
  php8.2-zip \
  php8.2-intl \
  php8.2-bcmath \
  php8.2-apcu

# 3. Configure Apache Virtual Host
echo "🌐 Configuring Apache Virtual Host..."
a2enmod rewrite

cat <<EOF > /etc/apache2/sites-available/mdm.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot ${REMOTE_PATH}

    <Directory ${REMOTE_PATH}>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/mdm_error.log
    CustomLog \${APACHE_LOG_DIR}/mdm_access.log combined
</VirtualHost>
EOF

a2dissite 000-default.conf || true
a2ensite mdm.conf
systemctl restart apache2

# 4. Install Composer globally
echo "📦 Installing Composer globally..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# 5. Install MySQL Server & Create database/users
echo "🗄️ Installing MySQL Server..."
apt-get install -y mysql-server
systemctl start mysql
systemctl enable mysql

echo "🔑 Provisioning MySQL Database..."
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# 6. Configure UFW Firewall rules
echo "🛡️ Configuring Firewall rules (UFW)..."
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw allow 8080/tcp # WebSocket server port
echo "y" | ufw enable

# 7. Create directory path & adjust ownership
echo "📁 Setting up Remote Directory Paths..."
mkdir -p "${REMOTE_PATH}"
chown -R www-data:www-data "${REMOTE_PATH}"
chmod -R 775 "${REMOTE_PATH}"

echo "=============================================================================="
echo "  ✅ MDM VPS Host Setup Completed Successfully!"
echo "  DB Name: ${DB_NAME}"
echo "  DB User: ${DB_USER}"
echo "  WebSocket Port: 8080 (Opened in Firewall)"
echo "=============================================================================="
