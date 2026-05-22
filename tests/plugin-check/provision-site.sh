#!/usr/bin/env sh
set -eu

cd /var/www/html

SITE_URL="${WPM_PLUGIN_CHECK_SITE_URL:-http://mandate-plugin-check.local}"
PLUGIN_CHECK_VERSION="${WPM_PLUGIN_CHECK_VERSION:?WPM_PLUGIN_CHECK_VERSION must be set by the Plugin Check runner}"

for _ in $(seq 1 60); do
	if wp core version --allow-root >/dev/null 2>&1; then
		break
	fi
	sleep 2
done

if ! wp config path --allow-root >/dev/null 2>&1; then
	wp config create \
		--dbname=wordpress \
		--dbuser=root \
		--dbpass=testpass \
		--dbhost=db:3306 \
		--skip-check \
		--allow-root
	wp config set WP_ENVIRONMENT_TYPE local --type=constant --allow-root
fi

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
	wp core install \
		--url="${SITE_URL}" \
		--title="Mandate Plugin Check" \
		--admin_user=admin \
		--admin_password=password \
		--admin_email=devnull@example.com \
		--skip-email \
		--allow-root
fi

if wp plugin is-installed plugin-check --allow-root >/dev/null 2>&1; then
	CURRENT_VERSION="$(wp plugin get plugin-check --field=version --allow-root 2>/dev/null || true)"
	if [ "${CURRENT_VERSION}" != "${PLUGIN_CHECK_VERSION}" ]; then
		wp plugin install plugin-check --version="${PLUGIN_CHECK_VERSION}" --force --activate --allow-root
	elif ! wp plugin is-active plugin-check --allow-root >/dev/null 2>&1; then
		wp plugin activate plugin-check --allow-root >/dev/null
	fi
else
	wp plugin install plugin-check --version="${PLUGIN_CHECK_VERSION}" --activate --allow-root
fi

if ! wp plugin is-active mandate --allow-root >/dev/null 2>&1; then
	wp plugin activate mandate --allow-root
fi
