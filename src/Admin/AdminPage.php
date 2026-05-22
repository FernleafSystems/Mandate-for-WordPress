<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Admin;

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityName;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Plugin;

class AdminPage {

	private const REQUIRED_CAPABILITY = 'manage_options';
	private const NONCE_ACTION = 'application_password_scoper_save_scope';
	private const NONCE_NAME = 'application_password_scoper_nonce';

	private ScopeRepository $scopeRepository;

	private ApplicationPasswordRepository $passwordRepository;

	private CapabilityCandidateProvider $candidateProvider;

	private MetaCapabilityRegistry $metaRegistry;

	public function __construct(
		ScopeRepository $scopeRepository,
		ApplicationPasswordRepository $passwordRepository,
		CapabilityCandidateProvider $candidateProvider,
		MetaCapabilityRegistry $metaRegistry
	) {
		$this->scopeRepository = $scopeRepository;
		$this->passwordRepository = $passwordRepository;
		$this->candidateProvider = $candidateProvider;
		$this->metaRegistry = $metaRegistry;
	}

	public function registerHooks() :void {
		add_action( 'admin_menu', [ $this, 'registerMenu' ] );
		add_action( 'admin_init', [ $this, 'handlePost' ] );
	}

	public function registerMenu() :void {
		add_management_page(
			__( 'Application Password Scoper', 'application-password-scoper' ),
			__( 'Application Password Scoper', 'application-password-scoper' ),
			self::REQUIRED_CAPABILITY,
			Plugin::MENU_SLUG,
			[ $this, 'render' ]
		);
	}

	public function handlePost() :void {
		if ( ( $_SERVER[ 'REQUEST_METHOD' ] ?? '' ) !== 'POST'
			|| $this->postScalar( 'application_password_scoper_action' ) === ''
		) {
			return;
		}

		if ( !current_user_can( self::REQUIRED_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage application password scopes.', 'application-password-scoper' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$userId = absint( $this->postScalar( 'user_id' ) );
		$uuid = ApplicationPasswordRepository::normalizeUuid( $this->postScalar( 'app_password_uuid' ) );
		$action = sanitize_key( $this->postScalar( 'application_password_scoper_action' ) );
		$message = 'invalid';

		if ( $userId > 0 && $uuid !== '' && $this->passwordRepository->userOwnsPassword( $userId, $uuid ) ) {
			if ( $action === 'clear_scope' ) {
				$this->scopeRepository->delete( $uuid );
				$message = 'cleared';
			}
			elseif ( $action === 'save_scope' ) {
				if ( $this->isSuperAdminUser( $userId ) ) {
					$message = 'super_admin_unsupported';
				}
				else {
					$candidates = $this->candidateProvider->forUser( $userId );
					$submittedCaps = $this->postScalarList( 'allowed_caps' );
					$submittedMetaCaps = $this->postScalarList( 'allowed_meta_caps' );

					$allowedCaps = array_intersect_key( CapabilityName::normalizeMap( $submittedCaps ), $candidates );
					$allowedMetaCaps = $this->metaRegistry->intersectSubmitted( $submittedMetaCaps );
					$this->scopeRepository->save( $uuid, $userId, $allowedCaps, $allowedMetaCaps, get_current_user_id() );
					$message = 'saved';
				}
			}
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'              => Plugin::MENU_SLUG,
					'user_id'           => $userId,
					'app_password_uuid' => $uuid,
					'application_password_scoper_message' => $message,
				],
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	public function render() :void {
		if ( !current_user_can( self::REQUIRED_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage application password scopes.', 'application-password-scoper' ) );
		}

		$selectedUserId = $this->selectedUserId();
		$passwords = $this->passwordRepository->forUser( $selectedUserId );
		$selectedUuid = $this->selectedPasswordUuid( $passwords );
		$scope = $selectedUuid !== '' ? $this->scopeRepository->find( $selectedUuid ) : null;
		$candidateCaps = $this->candidateProvider->forUser( $selectedUserId );
		$metaCaps = $this->metaRegistry->registered();
		$selectedCaps = $scope === null
			? $candidateCaps
			: array_intersect_key( $scope[ 'allowed_caps' ], $candidateCaps );
		$selectedMetaCaps = $scope === null
			? $metaCaps
			: array_intersect_key( $scope[ 'allowed_meta_caps' ], $metaCaps );

		echo '<div class="wrap application-password-scoper">';
		echo '<h1>'.esc_html__( 'Application Password Scoper', 'application-password-scoper' ).'</h1>';
		$this->renderMessage();
		$this->renderSelectionForm( $selectedUserId, $passwords, $selectedUuid );

		if ( $selectedUserId < 1 ) {
			echo '</div>';
			return;
		}

		if ( empty( $passwords ) ) {
			echo '<div class="notice notice-info"><p>'.esc_html__( 'The selected user has no application passwords.', 'application-password-scoper' ).'</p></div>';
			echo '</div>';
			return;
		}

		if ( $selectedUuid === '' ) {
			echo '<div class="notice notice-warning"><p>'.esc_html__( 'Select an application password before saving a scope.', 'application-password-scoper' ).'</p></div>';
			echo '</div>';
			return;
		}

		$this->renderPasswordDetails( $passwords, $selectedUuid, $scope );
		$this->renderScopeForm(
			$selectedUserId,
			$selectedUuid,
			$candidateCaps,
			$selectedCaps,
			$metaCaps,
			$selectedMetaCaps
		);
		$this->renderCheckboxScript();
		echo '</div>';
	}

	private function selectedUserId() :int {
		$userId = absint( $this->getScalar( 'user_id' ) );
		if ( $userId > 0 && get_userdata( $userId ) ) {
			return $userId;
		}

		return (int)get_current_user_id();
	}

	/**
	 * @param array<int,array<string,mixed>> $passwords
	 */
	private function selectedPasswordUuid( array $passwords ) :string {
		$requested = ApplicationPasswordRepository::normalizeUuid( $this->getScalar( 'app_password_uuid' ) );
		foreach ( $passwords as $password ) {
			$uuid = isset( $password[ 'uuid' ] ) ? ApplicationPasswordRepository::normalizeUuid( (string)$password[ 'uuid' ] ) : '';
			if ( $uuid !== '' && $uuid === $requested ) {
				return $uuid;
			}
		}

		foreach ( $passwords as $password ) {
			$uuid = isset( $password[ 'uuid' ] ) ? ApplicationPasswordRepository::normalizeUuid( (string)$password[ 'uuid' ] ) : '';
			if ( $uuid !== '' ) {
				return $uuid;
			}
		}

		return '';
	}

	/**
	 * @param array<int,array<string,mixed>> $passwords
	 */
	private function renderSelectionForm( int $selectedUserId, array $passwords, string $selectedUuid ) :void {
		echo '<form method="get" action="'.esc_url( admin_url( 'tools.php' ) ).'">';
		echo '<input type="hidden" name="page" value="'.esc_attr( Plugin::MENU_SLUG ).'" />';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="application-password-scoper-user">'.esc_html__( 'User', 'application-password-scoper' ).'</label></th><td>';
		wp_dropdown_users(
			[
				'name'     => 'user_id',
				'id'       => 'application-password-scoper-user',
				'selected' => $selectedUserId,
				'show'     => 'display_name_with_login',
			]
		);
		echo '</td></tr>';

		if ( !empty( $passwords ) ) {
			echo '<tr><th scope="row"><label for="application-password-scoper-password">'.esc_html__( 'Application Password', 'application-password-scoper' ).'</label></th><td>';
			echo '<select id="application-password-scoper-password" name="app_password_uuid">';
			foreach ( $passwords as $password ) {
				$uuid = isset( $password[ 'uuid' ] ) ? ApplicationPasswordRepository::normalizeUuid( (string)$password[ 'uuid' ] ) : '';
				if ( $uuid === '' ) {
					continue;
				}
				$name = isset( $password[ 'name' ] ) ? (string)$password[ 'name' ] : $uuid;
				echo '<option value="'.esc_attr( $uuid ).'" '.selected( $selectedUuid, $uuid, false ).'>'.esc_html( $name ).'</option>';
			}
			echo '</select>';
			echo '</td></tr>';
		}

		echo '</tbody></table>';
		submit_button( __( 'Load Selection', 'application-password-scoper' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * @param array<int,array<string,mixed>> $passwords
	 * @param array<string,mixed>|null $scope
	 */
	private function renderPasswordDetails( array $passwords, string $selectedUuid, ?array $scope ) :void {
		$password = null;
		foreach ( $passwords as $candidate ) {
			if ( isset( $candidate[ 'uuid' ] ) && ApplicationPasswordRepository::normalizeUuid( (string)$candidate[ 'uuid' ] ) === $selectedUuid ) {
				$password = $candidate;
				break;
			}
		}

		if ( $password === null ) {
			return;
		}

		echo '<h2>'.esc_html__( 'Selected Password', 'application-password-scoper' ).'</h2>';
		echo '<table class="widefat striped"><tbody>';
		$this->renderDetailRow( __( 'Name', 'application-password-scoper' ), (string)( $password[ 'name' ] ?? '' ) );
		$this->renderDetailRow( __( 'UUID', 'application-password-scoper' ), $selectedUuid );
		$this->renderDetailRow( __( 'App ID', 'application-password-scoper' ), (string)( $password[ 'app_id' ] ?? '' ) );
		$this->renderDetailRow( __( 'Created', 'application-password-scoper' ), $this->formatTimestamp( $password[ 'created' ] ?? null ) );
		$this->renderDetailRow( __( 'Last Used', 'application-password-scoper' ), $this->formatTimestamp( $password[ 'last_used' ] ?? null ) );
		$this->renderDetailRow(
			__( 'Scope', 'application-password-scoper' ),
			$scope === null ? __( 'Unrestricted', 'application-password-scoper' ) : __( 'Restricted', 'application-password-scoper' )
		);
		echo '</tbody></table>';
	}

	private function renderDetailRow( string $label, string $value ) :void {
		echo '<tr><th scope="row">'.esc_html( $label ).'</th><td>'.esc_html( $value === '' ? '-' : $value ).'</td></tr>';
	}

	/**
	 * @param array<string,true> $candidateCaps
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $metaCaps
	 * @param array<string,true> $selectedMetaCaps
	 */
	private function renderScopeForm(
		int $selectedUserId,
		string $selectedUuid,
		array $candidateCaps,
		array $selectedCaps,
		array $metaCaps,
		array $selectedMetaCaps
	) :void {
		echo '<h2>'.esc_html__( 'Capability Scope', 'application-password-scoper' ).'</h2>';

		if ( $this->isSuperAdminUser( $selectedUserId ) ) {
			echo '<div class="notice notice-warning"><p>'.esc_html__( 'Scopes for multisite super admins are not supported in this MVP.', 'application-password-scoper' ).'</p></div>';
		}

		echo '<form method="post" action="'.esc_url( admin_url( 'tools.php?page='.Plugin::MENU_SLUG ) ).'">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="user_id" value="'.esc_attr( (string)$selectedUserId ).'" />';
		echo '<input type="hidden" name="app_password_uuid" value="'.esc_attr( $selectedUuid ).'" />';

		$this->renderCheckboxControls();
		$this->renderCapabilityCheckboxes( 'allowed_caps', $candidateCaps, $selectedCaps, 'primitive' );
		$this->renderCapabilityCheckboxes( 'allowed_meta_caps', $metaCaps, $selectedMetaCaps, 'meta' );

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary" name="application_password_scoper_action" value="save_scope" '.disabled( $this->isSuperAdminUser( $selectedUserId ), true, false ).'>'.esc_html__( 'Save Scope', 'application-password-scoper' ).'</button> ';
		echo '<button type="submit" class="button" name="application_password_scoper_action" value="clear_scope">'.esc_html__( 'Clear Scope', 'application-password-scoper' ).'</button>';
		echo '</p>';
		echo '</form>';
	}

	private function renderCheckboxControls() :void {
		echo '<p>';
		echo '<button type="button" class="button" id="application-password-scoper-select-all">'.esc_html__( 'Select All', 'application-password-scoper' ).'</button> ';
		echo '<button type="button" class="button" id="application-password-scoper-deselect-all">'.esc_html__( 'Deselect All', 'application-password-scoper' ).'</button>';
		echo '</p>';
	}

	/**
	 * @param array<string,true> $capabilities
	 * @param array<string,true> $selected
	 */
	private function renderCapabilityCheckboxes( string $fieldName, array $capabilities, array $selected, string $group ) :void {
		echo '<h3>'.esc_html( $group === 'meta' ? __( 'Registered Meta Capabilities', 'application-password-scoper' ) : __( 'Role-Derived Primitive Capabilities', 'application-password-scoper' ) ).'</h3>';
		if ( empty( $capabilities ) ) {
			echo '<p>'.esc_html__( 'No capabilities are available for this selection.', 'application-password-scoper' ).'</p>';
			return;
		}

		echo '<fieldset id="application-password-scoper-'.$group.'-capabilities">';
		foreach ( array_keys( $capabilities ) as $capability ) {
			echo '<label style="display:inline-block;min-width:16rem;margin:0 1rem .5rem 0;">';
			echo '<input type="checkbox" name="'.esc_attr( $fieldName ).'[]" value="'.esc_attr( $capability ).'" '.checked( isset( $selected[ $capability ] ), true, false ).' /> ';
			echo '<code>'.esc_html( $capability ).'</code>';
			echo '</label>';
		}
		echo '</fieldset>';
	}

	private function renderCheckboxScript() :void {
		echo '<script>
document.addEventListener("click", function(event) {
	var check = event.target.closest("#application-password-scoper-select-all");
	var uncheck = event.target.closest("#application-password-scoper-deselect-all");
	if (!check && !uncheck) {
		return;
	}
	var form = event.target.closest("form");
	if (!form) {
		return;
	}
	form.querySelectorAll("input[type=checkbox][name=\"allowed_caps[]\"], input[type=checkbox][name=\"allowed_meta_caps[]\"]").forEach(function(input) {
		input.checked = !!check;
	});
});
</script>';
	}

	private function renderMessage() :void {
		$message = sanitize_key( $this->getScalar( 'application_password_scoper_message' ) );
		$messages = [
			'saved' => [ 'success', __( 'Scope saved.', 'application-password-scoper' ) ],
			'cleared' => [ 'success', __( 'Scope cleared.', 'application-password-scoper' ) ],
			'invalid' => [ 'error', __( 'The selected application password could not be verified for that user.', 'application-password-scoper' ) ],
			'super_admin_unsupported' => [ 'warning', __( 'Scopes for multisite super admins are not supported in this MVP.', 'application-password-scoper' ) ],
		];
		if ( !isset( $messages[ $message ] ) ) {
			return;
		}

		[ $type, $text ] = $messages[ $message ];
		echo '<div class="notice notice-'.esc_attr( $type ).' is-dismissible"><p>'.esc_html( $text ).'</p></div>';
	}

	private function formatTimestamp( mixed $timestamp ) :string {
		$timestamp = is_numeric( $timestamp ) ? (int)$timestamp : 0;
		if ( $timestamp < 1 ) {
			return __( 'Never', 'application-password-scoper' );
		}

		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $timestamp ) : date( 'Y-m-d H:i:s', $timestamp );
	}

	private function isSuperAdminUser( int $userId ) :bool {
		return function_exists( 'is_multisite' )
			&& is_multisite()
			&& function_exists( 'is_super_admin' )
			&& is_super_admin( $userId );
	}

	private function getScalar( string $key ) :string {
		return $this->requestScalar( $_GET, $key );
	}

	private function postScalar( string $key ) :string {
		return $this->requestScalar( $_POST, $key );
	}

	/**
	 * @return string[]
	 */
	private function postScalarList( string $key ) :array {
		$value = $_POST[ $key ] ?? [];
		if ( !is_array( $value ) ) {
			return [];
		}

		$items = [];
		foreach ( $value as $item ) {
			if ( is_scalar( $item ) ) {
				$items[] = (string)wp_unslash( $item );
			}
		}

		return $items;
	}

	/**
	 * @param array<string,mixed> $source
	 */
	private function requestScalar( array $source, string $key ) :string {
		$value = $source[ $key ] ?? '';
		return is_scalar( $value ) ? (string)wp_unslash( $value ) : '';
	}
}
