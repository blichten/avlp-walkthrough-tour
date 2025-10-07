#!/bin/bash
# AVLP Walkthrough Tour - Staging Deployment Script
# Following VLP deployment standards

set -e  # Exit on any error

# Configuration
PLUGIN_NAME="avlp-walkthrough-tour"
REMOTE_USER="u4-gb7cem5fkumj"
REMOTE_HOST="ssh.virtualleadershipprograms.com"
REMOTE_PORT="18765"
REMOTE_PATH="./www/staging9.virtualleadershipprograms.com/public_html/wp-content/plugins/$PLUGIN_NAME"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "default-walkthrough.php" ]; then
    print_error "default-walkthrough.php not found. Please run this script from the plugin root directory."
    exit 1
fi

# Check if plugin name matches
if [ "$(basename "$(pwd)")" != "$PLUGIN_NAME" ]; then
    print_warning "Current directory name doesn't match plugin name. Continuing anyway..."
fi

print_status "Deploying $PLUGIN_NAME to staging..."
print_status "Remote path: $REMOTE_PATH"

# Test SSH connection
print_status "Testing SSH connection..."
if ! ssh -p $REMOTE_PORT -o ConnectTimeout=10 $REMOTE_USER@$REMOTE_HOST "echo 'SSH connection successful'" > /dev/null 2>&1; then
    print_error "Failed to connect to staging server. Please check your SSH configuration."
    exit 1
fi
print_success "SSH connection established"

# Create remote directory if it doesn't exist
print_status "Ensuring remote directory exists..."
ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "mkdir -p $REMOTE_PATH"

# Deploy main plugin file
print_status "Deploying main plugin file..."
if scp -P $REMOTE_PORT default-walkthrough.php $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/; then
    print_success "Main plugin file deployed"
else
    print_error "Failed to deploy main plugin file"
    exit 1
fi

# Deploy includes directory
if [ -d "includes" ]; then
    print_status "Deploying includes directory..."
    if scp -r -P $REMOTE_PORT includes/ $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/; then
        print_success "Includes directory deployed"
    else
        print_error "Failed to deploy includes directory"
        exit 1
    fi
fi

# Deploy CSS directory
if [ -d "css" ]; then
    print_status "Deploying CSS directory..."
    if scp -r -P $REMOTE_PORT css/ $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/; then
        print_success "CSS directory deployed"
    else
        print_error "Failed to deploy CSS directory"
        exit 1
    fi
fi

# Deploy JavaScript directory
if [ -d "js" ]; then
    print_status "Deploying JavaScript directory..."
    if scp -r -P $REMOTE_PORT js/ $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/; then
        print_success "JavaScript directory deployed"
    else
        print_error "Failed to deploy JavaScript directory"
        exit 1
    fi
fi

# Deploy tests directory (for reference)
if [ -d "tests" ]; then
    print_status "Deploying tests directory..."
    if scp -r -P $REMOTE_PORT tests/ $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/; then
        print_success "Tests directory deployed"
    else
        print_warning "Failed to deploy tests directory (non-critical)"
    fi
fi

# Deploy monitoring directory
if [ -d "monitoring" ]; then
    print_status "Deploying monitoring directory..."
    if scp -r -P $REMOTE_PORT monitoring/ $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/; then
        print_success "Monitoring directory deployed"
    else
        print_warning "Failed to deploy monitoring directory (non-critical)"
    fi
fi

# Deploy any additional files
for file in *.md *.txt *.json; do
    if [ -f "$file" ]; then
        print_status "Deploying $file..."
        if scp -P $REMOTE_PORT "$file" $REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/; then
            print_success "$file deployed"
        else
            print_warning "Failed to deploy $file (non-critical)"
        fi
    fi
done

# Set proper permissions
print_status "Setting file permissions..."
ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "find $REMOTE_PATH -type f -name '*.php' -exec chmod 644 {} \;"
ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "find $REMOTE_PATH -type f -name '*.css' -exec chmod 644 {} \;"
ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "find $REMOTE_PATH -type f -name '*.js' -exec chmod 644 {} \;"
ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "find $REMOTE_PATH -type d -exec chmod 755 {} \;"

print_success "File permissions set"

# Verify deployment
print_status "Verifying deployment..."
if ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "test -f $REMOTE_PATH/default-walkthrough.php"; then
    print_success "Main plugin file verified"
else
    print_error "Main plugin file not found after deployment"
    exit 1
fi

# Check plugin activation
print_status "Checking plugin status..."
PLUGIN_STATUS=$(ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "cd /home/customer/www/staging9.virtualleadershipprograms.com/public_html && wp plugin status $PLUGIN_NAME --format=json" 2>/dev/null || echo "not_found")

if [[ "$PLUGIN_STATUS" == *"active"* ]]; then
    print_success "Plugin is active on staging"
elif [[ "$PLUGIN_STATUS" == *"inactive"* ]]; then
    print_warning "Plugin is installed but inactive on staging"
    print_status "Activating plugin..."
    if ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "cd /home/customer/www/staging9.virtualleadershipprograms.com/public_html && wp plugin activate $PLUGIN_NAME"; then
        print_success "Plugin activated"
    else
        print_error "Failed to activate plugin"
        exit 1
    fi
else
    print_warning "Plugin not found in WordPress. You may need to activate it manually."
fi

# Run database migrations if needed
print_status "Checking for database migrations..."
ssh -p $REMOTE_PORT $REMOTE_USER@$REMOTE_HOST "cd /home/customer/www/staging9.virtualleadershipprograms.com/public_html && wp eval 'if (function_exists(\"vlp_walkthrough_check_migrations\")) { vlp_walkthrough_check_migrations(); echo \"Migrations checked\"; } else { echo \"Migration function not available\"; }'"

print_success "✅ Deployment completed successfully!"
print_status "Plugin: $PLUGIN_NAME"
print_status "Environment: Staging"
print_status "URL: https://staging9.virtualleadershipprograms.com/wp-admin/plugins.php"

# Optional: Open staging admin in browser (macOS)
if command -v open > /dev/null 2>&1; then
    read -p "Would you like to open the staging admin in your browser? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        open "https://staging9.virtualleadershipprograms.com/wp-admin/plugins.php"
    fi
fi

print_status "Deployment script completed."
