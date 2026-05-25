<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityDescriptionProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityName;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\Mandate\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\Mandate\Plugin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-import-type ApplicationPasswordRecord from ApplicationPasswordRepository
 * @phpstan-import-type CapabilityScopeRecord from ScopeRepository
 */
class AdminPage {

	private const REQUIRED_CAPABILITY = 'manage_options';
	private const NONCE_ACTION_PREFIX = 'mandate_scope';
	private const ASSET_HANDLE = 'mandate-admin-page';
	private const FORM_ACTIONS = [
		'save_scope'  => true,
		'clear_scope' => true,
	];

	private ScopeRepository $scopeRepository;

	private ApplicationPasswordRepository $passwordRepository;

	private CapabilityCandidateProvider $candidateProvider;

	private CapabilityDescriptionProvider $descriptionProvider;

	private MetaCapabilityRegistry $metaRegistry;

	private CapabilityGroupProvider $groupProvider;

	private string $pluginFile;

	private ExpirationDatePolicy $expirationDatePolicy;

	private string $pageHookSuffix = '';

	public function __construct(
		ScopeRepository $scopeRepository,
		ApplicationPasswordRepository $passwordRepository,
		CapabilityCandidateProvider $candidateProvider,
		CapabilityDescriptionProvider $descriptionProvider,
		MetaCapabilityRegistry $metaRegistry,
		CapabilityGroupProvider $groupProvider,
		string $pluginFile,
		ExpirationDatePolicy $expirationDatePolicy
	) {
		$this->scopeRepository = $scopeRepository;
		$this->passwordRepository = $passwordRepository;
		$this->candidateProvider = $candidateProvider;
		$this->descriptionProvider = $descriptionProvider;
		$this->metaRegistry = $metaRegistry;
		$this->groupProvider = $groupProvider;
		$this->pluginFile = $pluginFile;
		$this->expirationDatePolicy = $expirationDatePolicy;
	}

	public function registerHooks() :void {
		add_action( 'admin_menu', [ $this, 'registerMenu' ] );
		add_action( 'admin_init', [ $this, 'handlePost' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
	}

	public function registerMenu() :void {
		$this->pageHookSuffix = (string)add_management_page(
			__( 'Mandate', 'mandate' ),
			__( 'Mandate', 'mandate' ),
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
		if ( $this->requestMethod() !== 'POST' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only detects whether this plugin form was submitted before verifying the nonce.
		$hasFormAction = isset( $_POST[ 'mandate_action' ] );
		if ( !$hasFormAction ) {
			return;
		}

		$this->requireManageCapability();

		$userId = absint( $this->postScalar( 'user_id' ) );
		$uuid = ApplicationPasswordRepository::normalizeUuid( $this->postScalar( 'app_password_uuid' ) );
		$action = sanitize_key( $this->postScalar( 'mandate_action' ) );
		$message = 'invalid';

		if ( !isset( self::FORM_ACTIONS[ $action ] ) ) {
			$this->redirectAfterPost( $userId, $uuid, $message );
		}

		check_admin_referer(
			$this->nonceAction( $action, $userId, $uuid ),
			$this->nonceName( $action )
		);

		if ( $userId > 0 && $uuid !== '' && $this->passwordRepository->userOwnsPassword( $userId, $uuid ) ) {
			if ( $action === 'clear_scope' ) {
				$message = $this->scopeRepository->deleteForUser( $userId, $uuid ) ? 'reset' : 'invalid';
			}
			elseif ( $action === 'save_scope' ) {
				if ( $this->isSuperAdminUser( $userId ) ) {
					$message = 'super_admin_unsupported';
				}
				else {
					$candidates = $this->candidateProvider->forUser( $userId );
					$submittedCaps = $this->verifiedPostScalarList( 'allowed_caps' );
					$submittedMetaCaps = $this->verifiedPostScalarList( 'allowed_meta_caps' );
					$expiresOn = $this->postedExpirationDate();

					if ( $expiresOn === false ) {
						$message = 'invalid';
					}
					else {
						$allowedCaps = array_intersect_key( CapabilityName::normalizeMap( $submittedCaps ), $candidates );
						$allowedMetaCaps = $this->metaRegistry->intersectSubmitted( $submittedMetaCaps );
						$capabilitiesRestricted = !( $allowedCaps === $candidates && $allowedMetaCaps === $this->metaRegistry->registered() );
						if ( !$capabilitiesRestricted && $expiresOn === null ) {
							$message = $this->scopeRepository->deleteForUser( $userId, $uuid ) ? 'reset' : 'invalid';
						}
						else {
							$message = $this->scopeRepository->save(
								$uuid,
								$userId,
								$allowedCaps,
								$allowedMetaCaps,
								$this->roleSlugsForUser( $userId ),
								get_current_user_id(),
								$expiresOn,
								$capabilitiesRestricted
							) ? 'saved' : 'invalid';
						}
					}
				}
			}
		}

		$this->redirectAfterPost( $userId, $uuid, $message );
	}

	private function redirectAfterPost( int $userId, string $uuid, string $message ) :void {
		wp_safe_redirect(
			add_query_arg(
				[
					'page'              => Plugin::MENU_SLUG,
					'user_id'           => $userId,
					'app_password_uuid' => $uuid,
					'mandate_message'   => $message,
				],
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	private function requireManageCapability() :void {
		if ( !current_user_can( self::REQUIRED_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage application password scopes.', 'mandate' ) );
		}
	}

	public function render() :void {
		$this->requireManageCapability();

		$selectedUserId = $this->selectedUserId();
		$passwords = $this->passwordRepository->forUser( $selectedUserId );
		$selectedUuid = $this->selectedPasswordUuid( $passwords );
		$scope = $selectedUuid !== '' ? $this->scopeRepository->findForUser( $selectedUserId, $selectedUuid ) : null;
		$expiresOn = $scope === null ? null : $scope[ 'expires_on' ];
		$candidateCaps = $this->candidateProvider->forUser( $selectedUserId );
		$metaCaps = $this->metaRegistry->registered();
		$selectedCaps = $scope === null || !$scope[ 'capabilities_restricted' ]
			? $candidateCaps
			: array_intersect_key( $scope[ 'allowed_caps' ], $candidateCaps );
		$selectedMetaCaps = $scope === null || !$scope[ 'capabilities_restricted' ]
			? $metaCaps
			: array_intersect_key( $scope[ 'allowed_meta_caps' ], $metaCaps );
		$capabilityGroups = $this->groupProvider->group( $candidateCaps, $metaCaps );

		echo '<div class="wrap mandate">';
		echo '<h1>'.esc_html__( 'Mandate', 'mandate' ).'</h1>';
		$this->renderMessage();
		$this->renderSelectionForm( $selectedUserId, $passwords, $selectedUuid, $scope, $expiresOn );

		if ( $selectedUserId < 1 ) {
			echo '</div>';
			return;
		}

		if ( empty( $passwords ) ) {
			echo '<div class="notice notice-info"><p>'.esc_html__( 'The selected user has no application passwords.', 'mandate' ).'</p></div>';
			echo '</div>';
			return;
		}

		if ( $selectedUuid === '' ) {
			echo '<div class="notice notice-warning"><p>'.esc_html__( 'Select an application password before saving a scope.', 'mandate' ).'</p></div>';
			echo '</div>';
			return;
		}

		$this->renderScopeForm(
			$selectedUserId,
			$selectedUuid,
			$capabilityGroups,
			$selectedCaps,
			$selectedMetaCaps,
			$expiresOn
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
	 * @param list<ApplicationPasswordRecord> $passwords
	 */
	private function selectedPasswordUuid( array $passwords ) :string {
		if ( $passwords === [] ) {
			return '';
		}

		$requested = ApplicationPasswordRepository::normalizeUuid( $this->getScalar( 'app_password_uuid' ) );
		foreach ( $passwords as $password ) {
			if ( $password[ 'uuid' ] === $requested ) {
				return $password[ 'uuid' ];
			}
		}

		return $passwords[ 0 ][ 'uuid' ];
	}

	/**
	 * @param list<ApplicationPasswordRecord> $passwords
	 * @param CapabilityScopeRecord|null $scope
	 * @param string|null $expiresOn
	 */
	private function renderSelectionForm( int $selectedUserId, array $passwords, string $selectedUuid, ?array $scope, ?string $expiresOn ) :void {
		$currentRoleSlugs = $this->roleSlugsForUser( $selectedUserId );

		echo '<form method="get" action="'.esc_url( admin_url( 'tools.php' ) ).'" class="mandate-selection" data-wpm-selection-form>';
		echo '<input type="hidden" name="page" value="'.esc_attr( Plugin::MENU_SLUG ).'" />';
		echo '<div class="mandate-selection-grid">';
		echo '<div class="mandate-selection-column">';
		echo '<div class="mandate-field">';
		echo '<label for="mandate-user">'.esc_html__( 'User', 'mandate' ).'</label>';
		wp_dropdown_users(
			[
				'name'     => 'user_id',
				'id'       => 'mandate-user',
				'selected' => $selectedUserId,
				'show'     => 'display_name_with_login',
			]
		);
		echo '</div>';
		$this->renderRoleSummary( $currentRoleSlugs );
		echo '</div>';

		echo '<div class="mandate-selection-column">';
		echo '<div class="mandate-field">';
		echo '<label for="mandate-password">'.esc_html__( 'Application Password', 'mandate' ).'</label>';
		if ( !empty( $passwords ) ) {
			echo '<select id="mandate-password" name="app_password_uuid">';
			foreach ( $passwords as $password ) {
				echo '<option value="'.esc_attr( $password[ 'uuid' ] ).'" '.selected( $selectedUuid, $password[ 'uuid' ], false ).'>'.esc_html( $password[ 'name' ] ).'</option>';
			}
			echo '</select>';
		}
		else {
			echo '<p class="description">'.esc_html__( 'No application passwords are available for this user.', 'mandate' ).'</p>';
		}
		echo '</div>';
		echo '</div>';

		echo '<div class="mandate-selection-column">';
		$this->renderPasswordSummary( $passwords, $selectedUuid, $scope, $expiresOn, $currentRoleSlugs );
		echo '</div>';
		echo '</div>';
		echo '<p class="mandate-selection-status" data-wpm-selection-status hidden>'.esc_html__( 'Loading selection...', 'mandate' ).'</p>';
		echo '<noscript><p class="mandate-selection-fallback"><button type="submit" class="button button-secondary">'.esc_html__( 'Apply Selection', 'mandate' ).'</button></p></noscript>';
		echo '</form>';
	}

	/**
	 * @param list<string> $roleSlugs
	 */
	private function renderRoleSummary( array $roleSlugs ) :void {
		$roles = $this->roleSummaries( $roleSlugs );
		echo '<div id="mandate-role-summary" class="mandate-role-summary">';
		echo '<p class="mandate-role-summary-label">'.esc_html__( 'Roles for selected user', 'mandate' ).'</p>';
		if ( empty( $roles ) ) {
			echo '<p class="description">'.esc_html__( 'No roles assigned.', 'mandate' ).'</p>';
			echo '</div>';
			return;
		}

		echo '<ul>';
		foreach ( $roles as $role ) {
			echo '<li>';
			echo esc_html( $role[ 'name' ] ).' ';
			echo '<span class="mandate-role-slug">('.esc_html__( 'slug:', 'mandate' ).' <code>'.esc_html( $role[ 'slug' ] ).'</code>)</span>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * @return list<string>
	 */
	private function roleSlugsForUser( int $selectedUserId ) :array {
		$user = $selectedUserId > 0 ? get_userdata( $selectedUserId ) : false;
		if ( !is_object( $user ) || !isset( $user->roles ) || !is_array( $user->roles ) ) {
			return [];
		}

		$slugs = [];
		foreach ( $user->roles as $role ) {
			if ( !is_scalar( $role ) ) {
				continue;
			}

			$slug = sanitize_key( (string)$role );
			if ( $slug !== '' ) {
				$slugs[ $slug ] = $slug;
			}
		}

		ksort( $slugs, SORT_NATURAL );
		return array_values( $slugs );
	}

	/**
	 * @param list<string> $roleSlugs
	 * @return array<int,array{name:string,slug:string}>
	 */
	private function roleSummaries( array $roleSlugs ) :array {
		$wpRoles = function_exists( 'wp_roles' ) ? wp_roles() : null;
		$summaries = [];
		foreach ( $roleSlugs as $roleSlug ) {
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
	 * @param list<ApplicationPasswordRecord> $passwords
	 * @param CapabilityScopeRecord|null $scope
	 * @param string|null $expiresOn
	 * @param list<string> $currentRoleSlugs
	 */
	private function renderPasswordSummary( array $passwords, string $selectedUuid, ?array $scope, ?string $expiresOn, array $currentRoleSlugs ) :void {
		$password = $this->selectedPassword( $passwords, $selectedUuid );
		if ( $password === null ) {
			return;
		}

		echo '<div id="mandate-password-summary" class="mandate-password-summary">';
		echo '<h2>'.esc_html__( 'Selected Password', 'mandate' ).'</h2>';
		echo '<dl class="mandate-password-summary-details">';
		$this->renderDetailItem( __( 'Name', 'mandate' ), $password[ 'name' ] );
		$this->renderDetailItem( __( 'UUID', 'mandate' ), $password[ 'uuid' ] );
		$this->renderDetailItem( __( 'App ID', 'mandate' ), $password[ 'app_id' ] );
		$this->renderDetailItem( __( 'Created', 'mandate' ), $this->formatTimestamp( $password[ 'created' ] ) );
		$this->renderDetailItem( __( 'Last Used', 'mandate' ), $this->formatTimestamp( $password[ 'last_used' ] ) );
		echo '</dl>';
		echo '<div class="mandate-password-summary-divider" aria-hidden="true"></div>';
		echo '<dl class="mandate-password-summary-details">';
		$this->renderDetailItem(
			__( 'Restricted Scope', 'mandate' ),
			$this->formatRestrictedScope( $scope, $expiresOn )
		);
		$this->renderExpirationDetailItem( $expiresOn );
		if ( $scope !== null ) {
			$this->renderDetailItem( __( 'Scope Last Saved', 'mandate' ), $this->formatTimestamp( $scope[ 'updated_at' ] ) );
			$this->renderDetailItem(
				__( 'Roles When Saved', 'mandate' ),
				$scope[ 'roles_at_update' ] === null
					? __( 'Not recorded', 'mandate' )
					: $this->formatRoleSlugs( $scope[ 'roles_at_update' ] )
			);
		}
		echo '</dl>';
		if ( $scope !== null && $scope[ 'roles_at_update' ] !== null && $scope[ 'roles_at_update' ] !== $currentRoleSlugs ) {
			echo '<div class="notice notice-warning inline" data-wpm-role-snapshot-status="changed"><p>'.esc_html__( 'The selected user roles have changed since this Mandate record was saved. Review the saved restrictions before relying on this Application Password.', 'mandate' ).'</p></div>';
		}
		echo '</div>';
	}

	/**
	 * @param CapabilityScopeRecord|null $scope
	 */
	private function formatRestrictedScope( ?array $scope, ?string $expiresOn ) :string {
		$restrictions = [];
		if ( $scope !== null && $scope[ 'capabilities_restricted' ] ) {
			$restrictions[] = __( 'Capabilities', 'mandate' );
		}
		if ( $expiresOn !== null ) {
			$restrictions[] = __( 'Expiration date', 'mandate' );
		}

		return empty( $restrictions ) ? __( 'Unrestricted', 'mandate' ) : implode( ' / ', $restrictions );
	}

	/**
	 * @param list<string> $roleSlugs
	 */
	private function formatRoleSlugs( array $roleSlugs ) :string {
		return $roleSlugs === [] ? __( 'No roles', 'mandate' ) : implode( ', ', $roleSlugs );
	}

	/**
	 * @param list<ApplicationPasswordRecord> $passwords
	 * @return ApplicationPasswordRecord|null
	 */
	private function selectedPassword( array $passwords, string $selectedUuid ) :?array {
		foreach ( $passwords as $password ) {
			if ( $password[ 'uuid' ] === $selectedUuid ) {
				return $password;
			}
		}

		return null;
	}

	private function renderDetailItem( string $label, string $value ) :void {
		echo '<div class="mandate-password-summary-detail"><dt>'.esc_html( $label ).'</dt><dd>'.esc_html( $value === '' ? '-' : $value ).'</dd></div>';
	}

	private function renderExpirationDetailItem( ?string $expiresOn ) :void {
		$expired = $this->expirationDatePolicy->isExpired( $expiresOn );
		$state = $expiresOn === null ? 'never' : ( $expired ? 'expired' : 'date' );
		$value = $expiresOn === null
			? __( 'Never expires', 'mandate' )
			// translators: %s: Application Password expiration date.
			: ( $expired ? sprintf( __( '%s (expired)', 'mandate' ), $expiresOn ) : $expiresOn );
		$classes = 'button-link mandate-expiration-summary'.( $expired ? ' is-expired' : '' );
		echo '<div class="mandate-password-summary-detail"><dt>'.esc_html__( 'Expiration Date', 'mandate' ).'</dt><dd>';
		echo '<button type="button" class="'.esc_attr( $classes ).'" data-wpm-expiration-summary data-wpm-expiration-state="'.esc_attr( $state ).'" aria-controls="mandate-expiration-date">'.esc_html( $value ).'</button>';
		echo '</dd></div>';
	}

	/**
	 * @param array{wordpress:array{primitive:array<string,true>,meta:array<string,true>},other:array{primitive:array<string,true>,meta:array<string,true>}} $capabilityGroups
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 * @param string|null $expiresOn
	 */
	private function renderScopeForm(
		int $selectedUserId,
		string $selectedUuid,
		array $capabilityGroups,
		array $selectedCaps,
		array $selectedMetaCaps,
		?string $expiresOn
	) :void {
		echo '<h2>'.esc_html__( 'Capability Scope', 'mandate' ).'</h2>';

		if ( $this->isSuperAdminUser( $selectedUserId ) ) {
			echo '<div class="notice notice-warning"><p>'.esc_html__( 'Scopes for multisite super admins are not supported.', 'mandate' ).'</p></div>';
		}

		echo '<form method="post" action="'.esc_url( admin_url( 'tools.php?page='.Plugin::MENU_SLUG ) ).'" class="mandate-scope-form">';
		wp_nonce_field(
			$this->nonceAction( 'save_scope', $selectedUserId, $selectedUuid ),
			$this->nonceName( 'save_scope' )
		);
		wp_nonce_field(
			$this->nonceAction( 'clear_scope', $selectedUserId, $selectedUuid ),
			$this->nonceName( 'clear_scope' )
		);
		echo '<input type="hidden" name="user_id" value="'.esc_attr( (string)$selectedUserId ).'" />';
		echo '<input type="hidden" name="app_password_uuid" value="'.esc_attr( $selectedUuid ).'" />';
		echo '<div class="mandate-field mandate-expiration-field">';
		echo '<label for="mandate-expiration-date">'.esc_html__( 'Expiration Date', 'mandate' ).'</label>';
		echo '<input type="date" id="mandate-expiration-date" name="expiration_date" value="'.esc_attr( $expiresOn ?? '' ).'" data-wpm-expiration-input />';
		echo '<p class="description">'.esc_html__( 'Leave empty for no expiration.', 'mandate' ).'</p>';
		echo '</div>';

		echo '<div class="mandate-tabs" role="tablist" aria-label="'.esc_attr__( 'Capability groups', 'mandate' ).'">';
		$this->renderTabButton( 'wordpress', __( 'WordPress', 'mandate' ), true );
		$this->renderTabButton( 'other', __( 'Everything Else', 'mandate' ), false );
		echo '</div>';

		$this->renderCapabilityPanel( 'wordpress', $capabilityGroups[ 'wordpress' ], $selectedCaps, $selectedMetaCaps );
		$this->renderCapabilityPanel( 'other', $capabilityGroups[ 'other' ], $selectedCaps, $selectedMetaCaps );

		echo '<p class="submit mandate-actions">';
		echo '<button type="submit" class="button button-primary" name="mandate_action" value="save_scope" '.disabled( $this->isSuperAdminUser( $selectedUserId ), true, false ).'>'.esc_html__( 'Save Scope', 'mandate' ).'</button> ';
		echo '<button type="submit" class="button" name="mandate_action" value="clear_scope">'.esc_html__( 'Reset to Defaults', 'mandate' ).'</button>';
		echo '</p>';
		echo '</form>';
	}

	private function renderTabButton( string $groupKey, string $label, bool $active ) :void {
		echo '<button type="button" id="mandate-tab-'.esc_attr( $groupKey ).'" class="nav-tab'.( $active ? ' nav-tab-active' : '' ).'" role="tab" data-wpm-tab="'.esc_attr( $groupKey ).'" aria-controls="mandate-panel-'.esc_attr( $groupKey ).'" aria-selected="'.esc_attr( $active ? 'true' : 'false' ).'">'.esc_html( $label ).'</button>';
	}

	/**
	 * @param array{primitive:array<string,true>,meta:array<string,true>} $capabilities
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 */
	private function renderCapabilityPanel( string $groupKey, array $capabilities, array $selectedCaps, array $selectedMetaCaps ) :void {
		echo '<section id="mandate-panel-'.esc_attr( $groupKey ).'" class="mandate-capability-panel" data-wpm-panel="'.esc_attr( $groupKey ).'" aria-labelledby="mandate-tab-'.esc_attr( $groupKey ).'">';
		echo '<div class="mandate-panel-heading">';
		echo '<p>';
		echo '<button type="button" class="button" data-wpm-select-group="'.esc_attr( $groupKey ).'" data-wpm-select-state="checked">'.esc_html__( 'Select All', 'mandate' ).'</button> ';
		echo '<button type="button" class="button" data-wpm-select-group="'.esc_attr( $groupKey ).'" data-wpm-select-state="unchecked">'.esc_html__( 'Deselect All', 'mandate' ).'</button>';
		echo '</p>';
		echo '</div>';
		echo '<div class="mandate-capability-scroll">';
		$this->renderCapabilitySection( $groupKey, 'primitive', __( 'Role-Derived Primitive Capabilities', 'mandate' ), 'allowed_caps', $capabilities[ 'primitive' ], $selectedCaps );
		$this->renderCapabilitySection( $groupKey, 'meta', __( 'Registered Meta Capabilities', 'mandate' ), 'allowed_meta_caps', $capabilities[ 'meta' ], $selectedMetaCaps );
		echo '</div>';
		echo '</section>';
	}

	/**
	 * @param array<string,true> $capabilities
	 * @param array<string,true> $selected
	 */
	private function renderCapabilitySection( string $groupKey, string $type, string $label, string $fieldName, array $capabilities, array $selected ) :void {
		echo '<fieldset id="mandate-'.esc_attr( $groupKey ).'-'.esc_attr( $type ).'-capabilities" class="mandate-capability-section">';
		echo '<legend>'.esc_html( $label ).'</legend>';
		if ( empty( $capabilities ) ) {
			echo '<p class="description">'.esc_html__( 'No capabilities are available for this group.', 'mandate' ).'</p>';
			echo '</fieldset>';
			return;
		}

		echo '<div class="mandate-capability-list">';
		foreach ( array_keys( $capabilities ) as $capability ) {
			echo '<label>';
			echo '<input type="checkbox" name="'.esc_attr( $fieldName ).'[]" value="'.esc_attr( $capability ).'" '.checked( isset( $selected[ $capability ] ), true, false ).' /> ';
			$this->renderCapabilityName( $capability );
			echo '</label>';
		}
		echo '</div>';
		echo '</fieldset>';
	}

	private function renderCapabilityName( string $capability ) :void {
		$description = $this->descriptionProvider->descriptionFor( $capability );
		if ( $description === '' ) {
			echo '<code>'.esc_html( $capability ).'</code>';
			return;
		}

		echo '<code tabindex="0" data-wpm-tooltip data-wpm-tooltip-text="'.esc_attr( $description ).'">'.esc_html( $capability ).'</code>';
	}

	private function renderMessage() :void {
		$message = sanitize_key( $this->getScalar( 'mandate_message' ) );
		$messages = [
			'saved' => [ 'success', __( 'Scope saved.', 'mandate' ) ],
			'reset' => [ 'success', __( 'Scope reset to defaults.', 'mandate' ) ],
			'invalid' => [ 'error', __( 'The selected application password could not be verified for that user.', 'mandate' ) ],
			'super_admin_unsupported' => [ 'warning', __( 'Scopes for multisite super admins are not supported.', 'mandate' ) ],
		];
		if ( !isset( $messages[ $message ] ) ) {
			return;
		}

		[ $type, $text ] = $messages[ $message ];
		echo '<div class="notice notice-'.esc_attr( $type ).' is-dismissible"><p>'.esc_html( $text ).'</p></div>';
	}

	private function formatTimestamp( int $timestamp ) :string {
		if ( $timestamp < 1 ) {
			return __( 'Never', 'mandate' );
		}

		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $timestamp ) : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private function isSuperAdminUser( int $userId ) :bool {
		return function_exists( 'is_multisite' )
			&& is_multisite()
			&& function_exists( 'is_super_admin' )
			&& is_super_admin( $userId );
	}

	private function nonceAction( string $action, int $userId, string $uuid ) :string {
		return implode(
			':',
			[
				self::NONCE_ACTION_PREFIX,
				$action,
				(string)max( 0, $userId ),
				ApplicationPasswordRepository::normalizeUuid( $uuid ),
			]
		);
	}

	private function nonceName( string $action ) :string {
		return 'mandate_'.$action.'_nonce';
	}

	private function getScalar( string $key ) :string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sanitized non-mutating admin view-state query arg.
		return $this->requestScalar( $_GET, $key );
	}

	private function postScalar( string $key ) :string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sanitized action context is needed to select the scoped nonce before verification.
		return $this->requestScalar( $_POST, $key );
	}

	private function requestMethod() :string {
		$method = isset( $_SERVER[ 'REQUEST_METHOD' ] )
			? sanitize_key( wp_unslash( $_SERVER[ 'REQUEST_METHOD' ] ) )
			: '';

		return strtoupper( $method );
	}

	private function postedExpirationDate() :string|null|false {
		$submitted = $this->postScalar( 'expiration_date' );
		if ( $submitted === '' ) {
			return null;
		}

		return $this->expirationDatePolicy->normalize( $submitted ) ?? false;
	}

	/**
	 * @return string[]
	 */
	private function verifiedPostScalarList( string $key ) :array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in handlePost(); each list item is sanitized below.
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
