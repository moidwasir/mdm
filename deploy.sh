#!/bin/bash
# ============================================================
# MDM Control Center — Production Deployment Script
# ============================================================
# Usage:
#   chmod +x deploy.sh
#   ./deploy.sh --host your-server.com --user ubuntu --path /var/www/mdm
# ============================================================

set -e

HOST=""
SSH_USER="ubuntu"
REMOTE_PATH="/var/www/html/mdm"
LOCAL_PATH="/Applications/XAMPP/xamppfiles/htdocs/mdm"

# Parse arguments
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --host) HOST="$2"; shift ;;
        --user) SSH_USER="$2"; shift ;;
        --path) REMOTE_PATH="$2"; shift ;;
    esac
    shift
done

if [ -z "$HOST" ]; then
    echo "❌ Error: --host is required"
    echo "Usage: ./deploy.sh --host your-server.com --user ubuntu --path /var/www/html/mdm"
    exit 1
fi

echo ""
echo "============================================"
echo "  MDM Control Center — Deploying to Server"
echo "  Host: $SSH_USER@$HOST"
echo "  Path: $REMOTE_PATH"
echo "============================================"
echo ""

# Exclude local dev / build artifacts
EXCLUDES=(
    --exclude=".git"
    --exclude="android/"
    --exclude="vendor/"
    --exclude="*.log"
    --exclude=".DS_Store"
    --exclude="assets/uploads/*"
    --exclude="apk/*"
)

# Sync files to server
echo "📤 Syncing files..."
rsync -avz --progress "${EXCLUDES[@]}" "$LOCAL_PATH/" "$SSH_USER@$HOST:$REMOTE_PATH/"

# Install Composer dependencies on server
echo ""
echo "📦 Installing Composer dependencies on server..."
ssh "$SSH_USER@$HOST" "cd $REMOTE_PATH && composer install --no-dev --optimize-autoloader"

# Create required directories on server
echo ""
echo "📁 Creating required directories..."
ssh "$SSH_USER@$HOST" "mkdir -p $REMOTE_PATH/assets/uploads $REMOTE_PATH/apk && chmod 755 $REMOTE_PATH/assets/uploads $REMOTE_PATH/apk"

# Set correct file permissions
echo ""
echo "🔐 Setting file permissions..."
ssh "$SSH_USER@$HOST" "find $REMOTE_PATH -type f -name '*.php' -exec chmod 644 {} \; && find $REMOTE_PATH -type d -exec chmod 755 {} \;"

echo ""
echo "✅ Deployment complete!"
echo ""
echo "⚠️  IMPORTANT — Post-deployment checklist:"
echo "  1. Update config/constants.php APP_URL to your production domain"
echo "  2. Set FCM_SERVER_KEY in config/constants.php"
echo "  3. Import sql/schema.sql into your production MySQL database"
echo "  4. Configure Apache/Nginx to point to $REMOTE_PATH"
echo "  5. Set up SSL with: sudo certbot --apache -d your-domain.com"
echo "  6. Start WebSocket server: php websocket/server.php &"
echo ""
