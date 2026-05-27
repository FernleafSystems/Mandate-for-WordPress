<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate;

use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ApplicationPasswordExpirationReaper;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	public const VERSION = '0.4.1';
	public const MENU_SLUG = PluginIdentity::SLUG;

	public static function boot( string $pluginFile ) :void {
		( new self() )->register( $pluginFile );
	}

	private function register( string $pluginFile ) :void {
		$services = new PluginServices( $pluginFile );
		$this->registerGlobalHooks( $services, $pluginFile );
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			$this->registerAdminHooks( $services, $pluginFile );
		}
	}

	private function registerGlobalHooks( PluginServices $services, string $pluginFile ) :void {
		add_action(
			'application_password_did_authenticate',
			static function ( mixed $user, array $item ) use ( $services ) :void {
				$services->currentApplicationPasswordContext()->captureAuthenticatedPassword( $user, $item );
			},
			20,
			2
		);
		add_filter(
			'user_has_cap',
			static fn( array $allcaps, array $caps, array $args, mixed $user ) :array =>
				$services->capabilityScopeEnforcer()->filterUserCapabilities( $allcaps, $caps, $args, $user ),
			PHP_INT_MAX,
			4
		);
		add_filter(
			'map_meta_cap',
			static fn( array $caps, string $cap, int $userId, array $args ) :array =>
				$services->capabilityScopeEnforcer()->filterMetaCaps( $caps, $cap, $userId, $args ),
			PHP_INT_MAX,
			4
		);
		add_action(
			'wp_delete_application_password',
			static function ( int $userId, array $item ) use ( $services ) :void {
				$services->scopeRepository()->deleteForApplicationPassword( $userId, $item );
			},
			10,
			2
		);
		add_action(
			ApplicationPasswordExpirationReaper::HOOK,
			static function () use ( $services ) :void {
				$services->expirationReaper()->revokeExpiredApplicationPasswords();
			}
		);
		ApplicationPasswordExpirationReaper::ensureScheduledHook();
		if ( function_exists( 'register_deactivation_hook' ) ) {
			register_deactivation_hook( $pluginFile, [ ApplicationPasswordExpirationReaper::class, 'clearScheduledHook' ] );
		}
	}

	private function registerAdminHooks( PluginServices $services, string $pluginFile ) :void {
		add_filter( 'plugin_action_links_'.plugin_basename( $pluginFile ), [ $this, 'addSettingsActionLink' ] );
		add_action(
			'admin_menu',
			static function () use ( $services ) :void {
				$accessPolicy = $services->adminScopeAccessPolicy();
				if ( !$accessPolicy->canAccessPage() ) {
					return;
				}

				$pageHook = add_management_page(
					__( 'Mandate App Security', 'mandate-app-security' ),
					__( 'Mandate App Security', 'mandate-app-security' ),
					$accessPolicy->pageCapability(),
					self::MENU_SLUG,
					static function () use ( $services ) :void {
						$services->adminPage()->render();
					}
				);

				if ( !is_string( $pageHook ) || $pageHook === '' ) {
					return;
				}

				add_action(
					'load-'.$pageHook,
					static function () use ( $services, $pageHook ) :void {
						$services->adminPage()->handlePost();
						add_action(
							'admin_enqueue_scripts',
							static function ( string $currentHook ) use ( $services, $pageHook ) :void {
								if ( $currentHook !== $pageHook ) {
									return;
								}

								$services->adminPage()->enqueueAssets( $currentHook );
							}
						);
					}
				);
			}
		);
		add_filter(
			'manage_application-passwords-user_columns',
			static fn( array $columns ) :array => $services->applicationPasswordScopeColumn()->addColumn( $columns )
		);
		add_action(
			'manage_application-passwords-user_custom_column',
			static function ( string $columnName, array $item ) use ( $services ) :void {
				$services->applicationPasswordScopeColumn()->renderColumn( $columnName, $item );
			},
			10,
			2
		);
		add_action(
			'manage_application-passwords-user_custom_column_js_template',
			static function ( string $columnName ) use ( $services ) :void {
				$services->applicationPasswordScopeColumn()->renderColumnJsTemplate( $columnName );
			}
		);
	}

	/**
	 * @param array<int|string,string> $links
	 * @return array<int|string,string>
	 */
	public function addSettingsActionLink( array $links ) :array {
		$url = add_query_arg( [ 'page' => self::MENU_SLUG ], admin_url( 'tools.php' ) );

		return [
			'settings' => '<a href="'.esc_url( $url ).'">'.esc_html__( 'Settings', 'mandate-app-security' ).'</a>',
		] + $links;
	}
}
