<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Admin;

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityName;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Plugin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPage {

	private const REQUIRED_CAPABILITY = 'manage_options';
	private const NONCE_ACTION = 'application_password_scoper_save_scope';
	private const NONCE_NAME = 'application_password_scoper_nonce';
	private const ASSET_HANDLE = 'application-password-scoper-admin-page';

	private ScopeRepository $scopeRepository;

	private ApplicationPasswordRepository $passwordRepository;

	private CapabilityCandidateProvider $candidateProvider;

	private MetaCapabilityRegistry $metaRegistry;

	private CapabilityGroupProvider $groupProvider;

	private string $pluginFile;

	private string $pageHookSuffix = '';

	public function __construct(
		ScopeRepository $scopeRepository,
		ApplicationPasswordRepository $passwordRepository,
		CapabilityCandidateProvider $candidateProvider,
		MetaCapabilityRegistry $metaRegistry,
		CapabilityGroupProvider $groupProvider,
		string $pluginFile
	) {
		$this->scopeRepository = $scopeRepository;
		$this->passwordRepository = $passwordRepository;
		$this->candidateProvider = $candidateProvider;
		$this->metaRegistry = $metaRegistry;
		$this->groupProvider = $groupProvider;
		$this->pluginFile = $pluginFile;
	}

	public function registerHooks() :void {
		add_action( 'admin_menu', [ $this, 'registerMenu' ] );
		add_action( 'admin_init', [ $this, 'handlePost' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
	}

	public function registerMenu() :void {
		$this->pageHookSuffix = (string)add_management_page(
			__( 'Application Password Scoper', 'application-password-scoper' ),
			__( 'Application Password Scoper', 'application-password-scoper' ),
			self::REQUIRED_CAPABILITY,
			Plugin::MENU_SLUG,
			[ $this, 'render' ]
		);
	}

	public function enqueueAssets( string $hookSuffix ) :void {
		if ( $this->pageHookSuffix === '' || $hookSuffix !== $this->pageHookSuffix ) {
			return;
		}

		$distPath = plugin_dir_path( $this->pluginFile ).'assets/dist/';
		$distUrl = plugin_dir_url( $this->pluginFile ).'assets/dist/';
		$cssPath = $distPath.'admin-page.css';
		$jsPath = $distPath.'admin-page.js';

		if ( is_file( $cssPath ) ) {
			wp_enqueue_style(
				self::ASSET_HANDLE,
				$distUrl.'admin-page.css',
				[],
				(string)filemtime( $cssPath )
			);
		}

		if ( is_file( $jsPath ) ) {
			wp_enqueue_script(
				self::ASSET_HANDLE,
				$distUrl.'admin-page.js',
				[],
				(string)filemtime( $jsPath ),
				true
			);
		}
	}

	public function handlePost() :void {
		if ( $this->requestMethod() !== 'POST'
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
				$message = 'reset';
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
		$capabilityGroups = $this->groupProvider->group( $candidateCaps, $metaCaps );

		echo '<div class="wrap application-password-scoper">';
		echo '<h1>'.esc_html__( 'Application Password Scoper', 'application-password-scoper' ).'</h1>';
		$this->renderMessage();
		$this->renderSelectionForm( $selectedUserId, $passwords, $selectedUuid, $scope );

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

		$this->renderScopeForm(
			$selectedUserId,
			$selectedUuid,
			$capabilityGroups,
			$selectedCaps,
			$selectedMetaCaps
		);
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
	 * @param array<string,mixed>|null $scope
	 */
	private function renderSelectionForm( int $selectedUserId, array $passwords, string $selectedUuid, ?array $scope ) :void {
		echo '<form method="get" action="'.esc_url( admin_url( 'tools.php' ) ).'" class="application-password-scoper-selection" data-aps-selection-form>';
		echo '<input type="hidden" name="page" value="'.esc_attr( Plugin::MENU_SLUG ).'" />';
		echo '<div class="application-password-scoper-selection-grid">';
		echo '<div class="application-password-scoper-selection-column">';
		echo '<div class="application-password-scoper-field">';
		echo '<label for="application-password-scoper-user">'.esc_html__( 'User', 'application-password-scoper' ).'</label>';
		wp_dropdown_users(
			[
				'name'     => 'user_id',
				'id'       => 'application-password-scoper-user',
				'selected' => $selectedUserId,
				'show'     => 'display_name_with_login',
			]
		);
		echo '</div>';
		$this->renderRoleSummary( $selectedUserId );
		echo '</div>';

		echo '<div class="application-password-scoper-selection-column">';
		echo '<div class="application-password-scoper-field">';
		echo '<label for="application-password-scoper-password">'.esc_html__( 'Application Password', 'application-password-scoper' ).'</label>';
		if ( !empty( $passwords ) ) {
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
		}
		else {
			echo '<p class="description">'.esc_html__( 'No application passwords are available for this user.', 'application-password-scoper' ).'</p>';
		}
		echo '</div>';
		$this->renderPasswordSummary( $passwords, $selectedUuid, $scope );
		echo '</div>';
		echo '</div>';
		submit_button( __( 'Load Selection', 'application-password-scoper' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	private function renderRoleSummary( int $selectedUserId ) :void {
		$roles = $this->roleSummaries( $selectedUserId );
		echo '<div id="application-password-scoper-role-summary" class="application-password-scoper-role-summary">';
		if ( empty( $roles ) ) {
			echo '<p class="description">'.esc_html__( 'No roles assigned.', 'application-password-scoper' ).'</p>';
			echo '</div>';
			return;
		}

		echo '<ul>';
		foreach ( $roles as $role ) {
			echo '<li>'.esc_html( $role[ 'name' ] ).' <code>'.esc_html( $role[ 'slug' ] ).'</code></li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * @return array<int,array{name:string,slug:string}>
	 */
	private function roleSummaries( int $selectedUserId ) :array {
		$user = $selectedUserId > 0 ? get_userdata( $selectedUserId ) : false;
		if ( !is_object( $user ) || !isset( $user->roles ) || !is_array( $user->roles ) ) {
			return [];
		}

		$wpRoles = function_exists( 'wp_roles' ) ? wp_roles() : null;
		$summaries = [];
		foreach ( array_unique( array_filter( array_map( 'strval', $user->roles ) ) ) as $roleSlug ) {
			$registered = is_object( $wpRoles ) && method_exists( $wpRoles, 'get_role' )
				? $wpRoles->get_role( $roleSlug )
				: null;
			$summaries[] = [
				'name' => $registered === null ? $roleSlug : $this->roleDisplayName( $roleSlug, $wpRoles ),
				'slug' => $roleSlug,
			];
		}

		return $summaries;
	}

	private function roleDisplayName( string $roleSlug, mixed $wpRoles ) :string {
		$roleNames = [];
		if ( is_object( $wpRoles ) && method_exists( $wpRoles, 'get_names' ) ) {
			$roleNames = $wpRoles->get_names();
		}
		elseif ( is_object( $wpRoles ) && isset( $wpRoles->role_names ) && is_array( $wpRoles->role_names ) ) {
			$roleNames = $wpRoles->role_names;
		}

		$name = isset( $roleNames[ $roleSlug ] ) ? (string)$roleNames[ $roleSlug ] : $roleSlug;
		return function_exists( 'translate_user_role' ) ? translate_user_role( $name ) : $name;
	}

	/**
	 * @param array<int,array<string,mixed>> $passwords
	 * @param array<string,mixed>|null $scope
	 */
	private function renderPasswordSummary( array $passwords, string $selectedUuid, ?array $scope ) :void {
		$password = $this->selectedPassword( $passwords, $selectedUuid );
		if ( $password === null ) {
			return;
		}

		echo '<div id="application-password-scoper-password-summary" class="application-password-scoper-password-summary">';
		echo '<h2>'.esc_html__( 'Selected Password', 'application-password-scoper' ).'</h2>';
		echo '<dl>';
		$this->renderDetailItem( __( 'Name', 'application-password-scoper' ), (string)( $password[ 'name' ] ?? '' ) );
		$this->renderDetailItem( __( 'UUID', 'application-password-scoper' ), $selectedUuid );
		$this->renderDetailItem( __( 'App ID', 'application-password-scoper' ), (string)( $password[ 'app_id' ] ?? '' ) );
		$this->renderDetailItem( __( 'Created', 'application-password-scoper' ), $this->formatTimestamp( $password[ 'created' ] ?? null ) );
		$this->renderDetailItem( __( 'Last Used', 'application-password-scoper' ), $this->formatTimestamp( $password[ 'last_used' ] ?? null ) );
		$this->renderDetailItem(
			__( 'Scope', 'application-password-scoper' ),
			$scope === null ? __( 'Unrestricted', 'application-password-scoper' ) : __( 'Restricted', 'application-password-scoper' )
		);
		echo '</dl>';
		echo '</div>';
	}

	/**
	 * @param array<int,array<string,mixed>> $passwords
	 * @return array<string,mixed>|null
	 */
	private function selectedPassword( array $passwords, string $selectedUuid ) :?array {
		foreach ( $passwords as $password ) {
			if ( isset( $password[ 'uuid' ] )
				&& ApplicationPasswordRepository::normalizeUuid( (string)$password[ 'uuid' ] ) === $selectedUuid
			) {
				return $password;
			}
		}

		return null;
	}

	private function renderDetailItem( string $label, string $value ) :void {
		echo '<div><dt>'.esc_html( $label ).'</dt><dd>'.esc_html( $value === '' ? '-' : $value ).'</dd></div>';
	}

	/**
	 * @param array{wordpress:array{primitive:array<string,true>,meta:array<string,true>},other:array{primitive:array<string,true>,meta:array<string,true>}} $capabilityGroups
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 */
	private function renderScopeForm(
		int $selectedUserId,
		string $selectedUuid,
		array $capabilityGroups,
		array $selectedCaps,
		array $selectedMetaCaps
	) :void {
		echo '<h2>'.esc_html__( 'Capability Scope', 'application-password-scoper' ).'</h2>';

		if ( $this->isSuperAdminUser( $selectedUserId ) ) {
			echo '<div class="notice notice-warning"><p>'.esc_html__( 'Scopes for multisite super admins are not supported in this MVP.', 'application-password-scoper' ).'</p></div>';
		}

		echo '<form method="post" action="'.esc_url( admin_url( 'tools.php?page='.Plugin::MENU_SLUG ) ).'" class="application-password-scoper-scope-form">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="user_id" value="'.esc_attr( (string)$selectedUserId ).'" />';
		echo '<input type="hidden" name="app_password_uuid" value="'.esc_attr( $selectedUuid ).'" />';

		echo '<div class="application-password-scoper-tabs" role="tablist" aria-label="'.esc_attr__( 'Capability groups', 'application-password-scoper' ).'">';
		$this->renderTabButton( 'wordpress', __( 'WordPress', 'application-password-scoper' ), true );
		$this->renderTabButton( 'other', __( 'Everything Else', 'application-password-scoper' ), false );
		echo '</div>';

		$this->renderCapabilityPanel( 'wordpress', __( 'WordPress', 'application-password-scoper' ), $capabilityGroups[ 'wordpress' ], $selectedCaps, $selectedMetaCaps );
		$this->renderCapabilityPanel( 'other', __( 'Everything Else', 'application-password-scoper' ), $capabilityGroups[ 'other' ], $selectedCaps, $selectedMetaCaps );

		echo '<p class="submit application-password-scoper-actions">';
		echo '<button type="submit" class="button button-primary" name="application_password_scoper_action" value="save_scope" '.disabled( $this->isSuperAdminUser( $selectedUserId ), true, false ).'>'.esc_html__( 'Save Scope', 'application-password-scoper' ).'</button> ';
		echo '<button type="submit" class="button" name="application_password_scoper_action" value="clear_scope">'.esc_html__( 'Reset to Defaults', 'application-password-scoper' ).'</button>';
		echo '</p>';
		echo '</form>';
	}

	private function renderTabButton( string $groupKey, string $label, bool $active ) :void {
		echo '<button type="button" id="application-password-scoper-tab-'.esc_attr( $groupKey ).'" class="nav-tab'.( $active ? ' nav-tab-active' : '' ).'" role="tab" data-aps-tab="'.esc_attr( $groupKey ).'" aria-controls="application-password-scoper-panel-'.esc_attr( $groupKey ).'" aria-selected="'.esc_attr( $active ? 'true' : 'false' ).'">'.esc_html( $label ).'</button>';
	}

	/**
	 * @param array{primitive:array<string,true>,meta:array<string,true>} $capabilities
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 */
	private function renderCapabilityPanel( string $groupKey, string $label, array $capabilities, array $selectedCaps, array $selectedMetaCaps ) :void {
		echo '<section id="application-password-scoper-panel-'.esc_attr( $groupKey ).'" class="application-password-scoper-capability-panel" data-aps-panel="'.esc_attr( $groupKey ).'" aria-labelledby="application-password-scoper-tab-'.esc_attr( $groupKey ).'">';
		echo '<div class="application-password-scoper-panel-heading">';
		echo '<h3>'.esc_html( $label ).'</h3>';
		echo '<p>';
		echo '<button type="button" class="button" data-aps-select-group="'.esc_attr( $groupKey ).'" data-aps-select-state="checked">'.esc_html__( 'Select All', 'application-password-scoper' ).'</button> ';
		echo '<button type="button" class="button" data-aps-select-group="'.esc_attr( $groupKey ).'" data-aps-select-state="unchecked">'.esc_html__( 'Deselect All', 'application-password-scoper' ).'</button>';
		echo '</p>';
		echo '</div>';
		echo '<div class="application-password-scoper-capability-scroll">';
		$this->renderCapabilitySection( $groupKey, 'primitive', __( 'Role-Derived Primitive Capabilities', 'application-password-scoper' ), 'allowed_caps', $capabilities[ 'primitive' ], $selectedCaps );
		$this->renderCapabilitySection( $groupKey, 'meta', __( 'Registered Meta Capabilities', 'application-password-scoper' ), 'allowed_meta_caps', $capabilities[ 'meta' ], $selectedMetaCaps );
		echo '</div>';
		echo '</section>';
	}

	/**
	 * @param array<string,true> $capabilities
	 * @param array<string,true> $selected
	 */
	private function renderCapabilitySection( string $groupKey, string $type, string $label, string $fieldName, array $capabilities, array $selected ) :void {
		echo '<fieldset id="application-password-scoper-'.esc_attr( $groupKey ).'-'.esc_attr( $type ).'-capabilities" class="application-password-scoper-capability-section">';
		echo '<legend>'.esc_html( $label ).'</legend>';
		if ( empty( $capabilities ) ) {
			echo '<p class="description">'.esc_html__( 'No capabilities are available for this group.', 'application-password-scoper' ).'</p>';
			echo '</fieldset>';
			return;
		}

		echo '<div class="application-password-scoper-capability-list">';
		foreach ( array_keys( $capabilities ) as $capability ) {
			echo '<label>';
			echo '<input type="checkbox" name="'.esc_attr( $fieldName ).'[]" value="'.esc_attr( $capability ).'" '.checked( isset( $selected[ $capability ] ), true, false ).' /> ';
			echo '<code>'.esc_html( $capability ).'</code>';
			echo '</label>';
		}
		echo '</div>';
		echo '</fieldset>';
	}

	private function renderMessage() :void {
		$message = sanitize_key( $this->getScalar( 'application_password_scoper_message' ) );
		$messages = [
			'saved' => [ 'success', __( 'Scope saved.', 'application-password-scoper' ) ],
			'reset' => [ 'success', __( 'Scope reset to defaults.', 'application-password-scoper' ) ],
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

		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $timestamp ) : gmdate( 'Y-m-d H:i:s', $timestamp );
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

	private function requestMethod() :string {
		$method = isset( $_SERVER[ 'REQUEST_METHOD' ] )
			? sanitize_key( wp_unslash( $_SERVER[ 'REQUEST_METHOD' ] ) )
			: '';

		return strtoupper( $method );
	}

	/**
	 * @return string[]
	 */
	private function postScalarList( string $key ) :array {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : [];
		if ( !is_array( $value ) ) {
			return [];
		}

		$items = [];
		foreach ( $value as $item ) {
			if ( is_scalar( $item ) ) {
				$items[] = sanitize_text_field( $item );
			}
		}

		return $items;
	}

	/**
	 * @param array<string,mixed> $source
	 */
	private function requestScalar( array $source, string $key ) :string {
		$value = $source[ $key ] ?? '';
		return is_scalar( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
	}
}
