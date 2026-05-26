<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Plugin;

class ApplicationPasswordScopeColumn {

	public const COLUMN_KEY = 'mandate_scope';

	private const REQUIRED_CAPABILITY = 'manage_options';
	private const JS_TEMPLATE_UUID = '{{ data.uuid }}';
	private const JS_URL_PLACEHOLDER = 'MANDATE_APPLICATION_PASSWORD_UUID';

	public function registerHooks() :void {
		add_filter( 'manage_application-passwords-user_columns', [ $this, 'addColumn' ] );
		add_action( 'manage_application-passwords-user_custom_column', [ $this, 'renderColumn' ], 10, 2 );
		add_action( 'manage_application-passwords-user_custom_column_js_template', [ $this, 'renderColumnJsTemplate' ] );
	}

	/**
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function addColumn( array $columns ) :array {
		if ( !$this->canManageScopes() ) {
			return $columns;
		}

		$withScopeColumn = [];
		$inserted = false;
		foreach ( $columns as $key => $label ) {
			if ( $key === 'revoke' ) {
				$withScopeColumn[ self::COLUMN_KEY ] = __( 'Scope', 'mandate-app-security' );
				$inserted = true;
			}

			$withScopeColumn[ $key ] = $label;
		}

		if ( !$inserted ) {
			$withScopeColumn[ self::COLUMN_KEY ] = __( 'Scope', 'mandate-app-security' );
		}

		return $withScopeColumn;
	}

	/**
	 * @param array<string,mixed> $item
	 */
	public function renderColumn( string $columnName, array $item ) :void {
		if ( $columnName !== self::COLUMN_KEY || !$this->canManageScopes() ) {
			return;
		}

		$this->renderScopeLink( $this->profileUserId(), $this->uuidFromItem( $item ) );
	}

	public function renderColumnJsTemplate( string $columnName ) :void {
		if ( $columnName !== self::COLUMN_KEY || !$this->canManageScopes() ) {
			return;
		}

		$userId = $this->profileUserId();
		if ( $userId < 1 ) {
			echo '&mdash;';
			return;
		}

		$this->renderScopeAnchor( $this->scopeUrl( $userId, self::JS_URL_PLACEHOLDER ), true );
	}

	private function renderScopeLink( int $userId, string $uuid ) :void {
		if ( $userId < 1 || $uuid === '' ) {
			echo '&mdash;';
			return;
		}

		$this->renderScopeAnchor( $this->scopeUrl( $userId, $uuid ), false );
	}

	private function renderScopeAnchor( string $href, bool $isJsTemplate ) :void {
		$href = esc_url( $href );
		if ( $isJsTemplate ) {
			$href = str_replace( self::JS_URL_PLACEHOLDER, self::JS_TEMPLATE_UUID, $href );
		}

		printf(
			'<a class="button" href="%1$s">%2$s</a>',
			$href,
			esc_html__( 'Restrict Scope', 'mandate-app-security' )
		);
	}

	private function canManageScopes() :bool {
		return current_user_can( self::REQUIRED_CAPABILITY );
	}

	private function profileUserId() :int {
		global $user_id;

		return isset( $user_id ) ? absint( $user_id ) : 0;
	}

	/**
	 * @param array<string,mixed> $item
	 */
	private function uuidFromItem( array $item ) :string {
		$uuid = $item[ 'uuid' ] ?? '';

		return is_scalar( $uuid ) ? ApplicationPasswordRepository::normalizeUuid( (string)$uuid ) : '';
	}

	private function scopeUrl( int $userId, string $uuid ) :string {
		return add_query_arg(
			[
				'page'              => Plugin::MENU_SLUG,
				'user_id'           => $userId,
				'app_password_uuid' => $uuid,
			],
			admin_url( 'tools.php' )
		);
	}
}
