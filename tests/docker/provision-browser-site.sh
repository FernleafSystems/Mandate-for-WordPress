#!/usr/bin/env sh
set -eu

cd /var/www/html

SITE_URL="${WPM_BROWSER_SITE_URL:-http://127.0.0.1:8898}"
ADMIN_USER="${WPM_BROWSER_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${WPM_BROWSER_ADMIN_PASSWORD:-password}"
ADMIN_EMAIL="${WPM_BROWSER_ADMIN_EMAIL:-devnull@example.com}"
DB_NAME="${WPM_BROWSER_DB_NAME:-wordpress_browser_lane_1}"
DB_HOST="${WPM_BROWSER_DB_HOST:-mandate-browser-db:3306}"
PLUGIN_SLUG="${WPM_BROWSER_PLUGIN_SLUG:-mandate-app-security}"
PLUGIN_MAIN="${PLUGIN_SLUG}/mandate-app-security.php"
FIXTURE_SOURCE="/app/tests/browser/fixtures/mandate-browser-fixture.php"
FIXTURE_TARGET="wp-content/mu-plugins/mandate-browser-fixture.php"

for _ in $(seq 1 60); do
	if wp core version --allow-root >/dev/null 2>&1; then
		break
	fi
	sleep 2
done

if [ ! -f wp-config.php ]; then
	wp config create \
		--dbname="${DB_NAME}" \
		--dbuser=root \
		--dbpass=testpass \
		--dbhost="${DB_HOST}" \
		--skip-check \
		--allow-root
fi

wp config set DB_NAME "${DB_NAME}" --type=constant --allow-root >/dev/null
wp config set DB_USER root --type=constant --allow-root >/dev/null
wp config set DB_PASSWORD testpass --type=constant --allow-root >/dev/null
wp config set DB_HOST "${DB_HOST}" --type=constant --allow-root >/dev/null
if ! wp config has WP_ENVIRONMENT_TYPE --allow-root >/dev/null 2>&1; then
	wp config set WP_ENVIRONMENT_TYPE local --type=constant --allow-root >/dev/null
fi

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
	wp core install \
		--url="${SITE_URL}" \
		--title="Mandate Browser Test" \
		--admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--skip-email \
		--allow-root
fi

wp option update siteurl "${SITE_URL}" --allow-root >/dev/null
wp option update home "${SITE_URL}" --allow-root >/dev/null

wp user update "${ADMIN_USER}" \
	--user_pass="${ADMIN_PASSWORD}" \
	--user_email="${ADMIN_EMAIL}" \
	--allow-root >/dev/null

if [ ! -f "wp-content/plugins/${PLUGIN_MAIN}" ]; then
	echo "Plugin runtime not found at wp-content/plugins/${PLUGIN_MAIN}" >&2
	exit 1
fi

mkdir -p wp-content/mu-plugins
cp "${FIXTURE_SOURCE}" "${FIXTURE_TARGET}"

wp plugin activate "${PLUGIN_SLUG}" --allow-root

cat > .htaccess <<'EOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF

wp option update permalink_structure '/%postname%/' --allow-root >/dev/null

wp eval 'wpm_browser_fixture_reset();' --allow-root >/dev/null
