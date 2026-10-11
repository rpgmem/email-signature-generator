#!/usr/bin/env bash
#
# Prepare the test environment for the functional suite:
#   - Download WordPress core into vendor/wordpress/ (idempotent)
#   - Optionally create a fresh MySQL database
#   - Write a wp-tests-config.php at the project root that wp-phpunit reads
#
# WordPress is downloaded directly from wordpress.org rather than via a
# composer package. The composer route requires the
# roots/wordpress-core-installer plugin to run, which behaves differently
# depending on whether composer plugins are allowed; downloading a tarball
# is reliable across local and CI environments.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [skip-db-create] [wp-version]

set -euo pipefail

if [ $# -lt 3 ]; then
    echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [skip-db-create] [wp-version]" >&2
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
SKIP_DB_CREATE=${5-false}
WP_VERSION=${6-latest}

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_CORE_DIR="$PROJECT_ROOT/vendor/wordpress"
WP_TESTS_DIR="$PROJECT_ROOT/vendor/wp-phpunit/wp-phpunit"

if [ ! -d "$WP_TESTS_DIR" ]; then
    echo "Run 'composer install' first — vendor/wp-phpunit/wp-phpunit is missing." >&2
    exit 1
fi

# 1. Download WordPress core if not already present.
if [ ! -f "$WP_CORE_DIR/wp-load.php" ]; then
    mkdir -p "$WP_CORE_DIR"
    if [ "$WP_VERSION" = "latest" ]; then
        ARCHIVE_URL="https://wordpress.org/latest.tar.gz"
    else
        ARCHIVE_URL="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    fi
    echo "Downloading WordPress from $ARCHIVE_URL"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$ARCHIVE_URL" -o "$PROJECT_ROOT/vendor/wordpress.tar.gz"
    elif command -v wget >/dev/null 2>&1; then
        wget -q -O "$PROJECT_ROOT/vendor/wordpress.tar.gz" "$ARCHIVE_URL"
    else
        echo "Neither curl nor wget is available." >&2
        exit 1
    fi
    tar --strip-components=1 -xzf "$PROJECT_ROOT/vendor/wordpress.tar.gz" -C "$WP_CORE_DIR"
    rm -f "$PROJECT_ROOT/vendor/wordpress.tar.gz"
    echo "WordPress installed at $WP_CORE_DIR"
else
    echo "WordPress already present at $WP_CORE_DIR — skipping download."
fi

# 2. Write wp-tests-config.php at the project root. The functional bootstrap
#    loads this via the WP_TESTS_CONFIG_FILE_PATH constant.
CONFIG_FILE="$PROJECT_ROOT/wp-tests-config.php"
cat > "$CONFIG_FILE" <<PHP
<?php
define( 'ABSPATH', '$WP_CORE_DIR/' );
define( 'WP_DEFAULT_THEME', 'default' );

define( 'DB_NAME',     '$DB_NAME' );
define( 'DB_USER',     '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST',     '$DB_HOST' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL',  'admin@example.org' );
define( 'WP_TESTS_TITLE',  'Test Site' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'AUTH_KEY',         'test-auth-key' );
define( 'SECURE_AUTH_KEY',  'test-secure-auth-key' );
define( 'LOGGED_IN_KEY',    'test-logged-in-key' );
define( 'NONCE_KEY',        'test-nonce-key' );
define( 'AUTH_SALT',        'test-auth-salt' );
define( 'SECURE_AUTH_SALT', 'test-secure-auth-salt' );
define( 'LOGGED_IN_SALT',   'test-logged-in-salt' );
define( 'NONCE_SALT',       'test-nonce-salt' );
PHP

# 3. Database creation (optional — typically the CI service container
#    already provisioned the DB via MYSQL_DATABASE).
if [ "$SKIP_DB_CREATE" = "true" ]; then
    echo "Skipping database creation. wp-tests-config.php written to $CONFIG_FILE."
    exit 0
fi

PARTS=(${DB_HOST//\:/ })
DB_HOSTNAME=${PARTS[0]}
DB_SOCK_OR_PORT=${PARTS[1]-}
EXTRA=""
if [ -n "$DB_HOSTNAME" ]; then
    if [[ "$DB_SOCK_OR_PORT" =~ ^[0-9]+$ ]]; then
        EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
    elif [ -n "$DB_SOCK_OR_PORT" ]; then
        EXTRA=" --socket=$DB_SOCK_OR_PORT"
    else
        EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
    fi
fi

mysqladmin --user="$DB_USER" --password="$DB_PASS" $EXTRA --force drop "$DB_NAME" 2>/dev/null || true
mysqladmin --user="$DB_USER" --password="$DB_PASS" $EXTRA create "$DB_NAME"

echo "Database $DB_NAME ready. wp-tests-config.php written to $CONFIG_FILE."
