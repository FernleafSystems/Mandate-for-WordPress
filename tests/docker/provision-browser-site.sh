#!/usr/bin/env sh
set -eu

cd /var/www/html

SITE_URL="${WPM_BROWSER_SITE_URL:-http://127.0.0.1:8898}"
ADMIN_USER="${WPM_BROWSER_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${WPM_BROWSER_ADMIN_PASSWORD:-password}"
ADMIN_EMAIL="${WPM_BROWSER_ADMIN_EMAIL:-devnull@example.com}"
PLUGIN_SLUG="mandate"
PLUGIN_MAIN="${PLUGIN_SLUG}/plugin.php"
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
		--title="Mandate Browser Test" \
		--admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--skip-email \
		--allow-root
fi

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

cat > /tmp/mandate-fixture.php <<'PHP'
<?php
$limitedRole = 'wpm_limited';
$otherRole = 'wpm_other';

remove_role( $limitedRole );
remove_role( $otherRole );
add_role(
	$limitedRole,
	'APS Limited',
	[
		'read'              => true,
		'edit_posts'        => true,
		'upload_files'      => true,
		'wpm_manage_widget' => true,
	]
);
add_role(
	$otherRole,
	'APS Other',
	[
		'read'           => true,
		'delete_posts'   => true,
		'manage_options' => true,
	]
);

function wpm_browser_fixture_user( string $login, string $email, string $role ) :WP_User {
	$user = get_user_by( 'login', $login );
	if ( $user instanceof WP_User ) {
		$user->set_role( $role );
		return $user;
	}

	$userId = wp_insert_user(
		[
			'user_login' => $login,
			'user_pass'  => 'password',
			'user_email' => $email,
			'role'       => $role,
		]
	);
	if ( is_wp_error( $userId ) ) {
		fwrite( STDERR, $userId->get_error_message().PHP_EOL );
		exit( 1 );
	}

	$user = get_user_by( 'id', (int)$userId );
	if ( !$user instanceof WP_User ) {
		fwrite( STDERR, 'Fixture user could not be loaded.'.PHP_EOL );
		exit( 1 );
	}

	return $user;
}

function wpm_browser_fixture_password( int $userId, string $name ) :array {
	[ $plainPassword, $item ] = WP_Application_Passwords::create_new_application_password(
		$userId,
		[
			'name'   => $name,
			'app_id' => wp_generate_uuid4(),
		]
	);

	return [
		'uuid'         => $item[ 'uuid' ],
		'app_password' => $plainPassword,
		'name'         => $name,
	];
}

$primaryUser = wpm_browser_fixture_user( 'wpm_user', 'wpm-user@example.com', $limitedRole );
$primaryUser->add_cap( 'delete_posts', true );
$otherUser = wpm_browser_fixture_user( 'wpm_other_user', 'wpm-other-user@example.com', $otherRole );

WP_Application_Passwords::delete_all_application_passwords( (int)$primaryUser->ID );
WP_Application_Passwords::delete_all_application_passwords( (int)$otherUser->ID );

$primaryPassword = wpm_browser_fixture_password( (int)$primaryUser->ID, 'WPM Browser Primary' );
$secondaryPassword = wpm_browser_fixture_password( (int)$primaryUser->ID, 'WPM Browser Secondary' );
$otherPassword = wpm_browser_fixture_password( (int)$otherUser->ID, 'WPM Browser Other' );

( new \FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository() )->replaceScopes( [] );
update_option(
	'mandate_browser_fixture',
	[
		'primary' => [
			'user_id'      => (int)$primaryUser->ID,
			'user_login'   => 'wpm_user',
			'role_slug'    => $limitedRole,
			'role_name'    => 'APS Limited',
			'passwords'    => [
				'primary'   => $primaryPassword,
				'secondary' => $secondaryPassword,
			],
			'role_caps'    => [ 'read', 'edit_posts', 'upload_files', 'wpm_manage_widget' ],
			'direct_cap'   => 'delete_posts',
		],
		'secondary_user' => [
			'user_id'      => (int)$otherUser->ID,
			'user_login'   => 'wpm_other_user',
			'role_slug'    => $otherRole,
			'role_name'    => 'APS Other',
			'passwords'    => [
				'primary' => $otherPassword,
			],
		],
		'unassigned_role_cap' => 'manage_options',
	],
	false
);
PHP

wp eval-file /tmp/mandate-fixture.php --allow-root
