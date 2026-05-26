<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityDescriptionProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityGroupProvider;
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
 * @phpstan-import-type AdminPageRenderData from AdminPageRenderContracts
 * @phpstan-import-type AdminNoticeContract from AdminPageRenderContracts
 * @phpstan-import-type AdminCapabilityPanelContract from AdminPageRenderContracts
 * @phpstan-import-type AdminCapabilitySectionContract from AdminPageRenderContracts
 * @phpstan-import-type AdminPageStringsContract from AdminPageRenderContracts
 * @phpstan-import-type AdminRoleSummaryContract from AdminPageRenderContracts
 * @phpstan-import-type AdminSelectionFormContract from AdminPageRenderContracts
 * @phpstan-import-type AdminPasswordSummaryContract from AdminPageRenderContracts
 * @phpstan-import-type AdminPasswordWarningContract from AdminPageRenderContracts
 * @phpstan-import-type AdminPasswordDetailExpirationContract from AdminPageRenderContracts
 * @phpstan-import-type AdminScopeFormContract from AdminPageRenderContracts
 */
class AdminPageViewDataBuilder {

	private const SCOPE_FORM_ID = 'mandate-scope-form';

	private ScopeRepository $scopeRepository;

	private ApplicationPasswordRepository $passwordRepository;

	private CapabilityCandidateProvider $candidateProvider;

	private CapabilityDescriptionProvider $descriptionProvider;

	private MetaCapabilityRegistry $metaRegistry;

	private CapabilityGroupProvider $groupProvider;

	private ExpirationDatePolicy $expirationDatePolicy;

	private AdminUserRoleProvider $roleProvider;

	private AdminScopeFormSecurity $formSecurity;

	private AdminTrustedHtmlSanitizer $trustedHtmlSanitizer;

	private AdminScopeAccessPolicy $accessPolicy;

	public function __construct(
		ScopeRepository $scopeRepository,
		ApplicationPasswordRepository $passwordRepository,
		CapabilityCandidateProvider $candidateProvider,
		CapabilityDescriptionProvider $descriptionProvider,
		MetaCapabilityRegistry $metaRegistry,
		CapabilityGroupProvider $groupProvider,
		ExpirationDatePolicy $expirationDatePolicy,
		AdminUserRoleProvider $roleProvider,
		AdminScopeFormSecurity $formSecurity,
		AdminTrustedHtmlSanitizer $trustedHtmlSanitizer,
		?AdminScopeAccessPolicy $accessPolicy = null
	) {
		$this->scopeRepository = $scopeRepository;
		$this->passwordRepository = $passwordRepository;
		$this->candidateProvider = $candidateProvider;
		$this->descriptionProvider = $descriptionProvider;
		$this->metaRegistry = $metaRegistry;
		$this->groupProvider = $groupProvider;
		$this->expirationDatePolicy = $expirationDatePolicy;
		$this->roleProvider = $roleProvider;
		$this->formSecurity = $formSecurity;
		$this->trustedHtmlSanitizer = $trustedHtmlSanitizer;
		$this->accessPolicy = $accessPolicy ?? new AdminScopeAccessPolicy();
	}

	/**
	 * @return AdminPageRenderData
	 */
	public function build() :array {
		$selectedUserId = $this->selectedUserId();
		$passwords = $this->passwordRepository->forUser( $selectedUserId );
		$selectedUuid = $this->selectedPasswordUuid( $passwords );
		$scope = $selectedUuid !== '' ? $this->scopeRepository->findForUser( $selectedUserId, $selectedUuid ) : null;
		$currentRoleSlugs = $this->roleProvider->roleSlugsForUser( $selectedUserId );
		$candidateCaps = $this->candidateProvider->forUser( $selectedUserId );
		$metaCaps = $this->metaRegistry->registered();
		$selectedCaps = $scope === null || !$scope[ 'capabilities_restricted' ]
			? $candidateCaps
			: array_intersect_key( $scope[ 'allowed_caps' ], $candidateCaps );
		$selectedMetaCaps = $scope === null || !$scope[ 'capabilities_restricted' ]
			? $metaCaps
			: array_intersect_key( $scope[ 'allowed_meta_caps' ], $metaCaps );
		$capabilityGroups = $this->groupProvider->group( $candidateCaps, $metaCaps );
		$isSuperAdmin = $this->isSuperAdminUser( $selectedUserId );
		$isReadOnly = $this->accessPolicy->isReadOnlyScope( $selectedUserId, $scope );
		$canManageAnyScope = $this->accessPolicy->canManageAnyScope();

		return [
			'hrefs'   => [
				'selection_form_action' => admin_url( 'tools.php' ),
				'scope_form_action'     => admin_url( 'tools.php?page='.Plugin::MENU_SLUG ),
			],
			'strings' => $this->buildStrings(),
			'flags'   => [
				'has_passwords'   => $passwords !== [],
				'show_scope_form' => $selectedUserId > 0 && $passwords !== [] && $selectedUuid !== '',
			],
			'classes' => [
				'root' => 'wrap mandate',
			],
			'vars'    => [
				'message'        => $this->buildRequestMessage(),
				'page_notice'    => $this->buildPageNotice( $selectedUserId, $passwords, $selectedUuid ),
				'selection_form' => $this->buildSelectionForm(
					$selectedUserId,
					$passwords,
					$selectedUuid,
					$scope,
					$currentRoleSlugs,
					$isReadOnly
				),
				'scope_form'     => $this->buildScopeForm(
					$selectedUserId,
					$selectedUuid,
					$isSuperAdmin,
					$scope !== null && $scope[ 'admin_locked' ],
					$isReadOnly,
					$canManageAnyScope,
					$capabilityGroups,
					$selectedCaps,
					$selectedMetaCaps
				),
			],
			'trustedHtml' => [
				'user_dropdown'      => $this->buildUserDropdown( $selectedUserId ),
				'scope_nonce_fields' => $selectedUuid === ''
					? ''
					: $this->formSecurity->nonceFields( $selectedUserId, $selectedUuid ),
			],
		];
	}

	/**
	 * @return AdminPageStringsContract
	 */
	private function buildStrings() :array {
		return [
			'page_title'                         => __( 'Mandate App Security', 'mandate-app-security' ),
			'user_label'                         => __( 'User', 'mandate-app-security' ),
			'application_password_label'         => __( 'Application Password', 'mandate-app-security' ),
			'role_slug_label'                    => __( 'slug:', 'mandate-app-security' ),
			'no_application_passwords_available' => __( 'No application passwords are available for this user.', 'mandate-app-security' ),
			'loading_selection'                  => __( 'Loading selection...', 'mandate-app-security' ),
			'apply_selection'                    => __( 'Apply Selection', 'mandate-app-security' ),
		];
	}

	/**
	 * @return AdminNoticeContract
	 */
	private function buildRequestMessage() :array {
		$message = sanitize_key( $this->getScalar( 'mandate_message' ) );
		$messages = [
			'saved'                   => [ 'success', __( 'Scope saved.', 'mandate-app-security' ) ],
			'reset'                   => [ 'success', __( 'Scope reset to defaults.', 'mandate-app-security' ) ],
			'invalid'                 => [ 'error', __( 'The selected application password could not be verified for that user.', 'mandate-app-security' ) ],
			'locked'                  => [ 'error', __( 'This application password scope is locked by an administrator and cannot be edited by its user.', 'mandate-app-security' ) ],
			'super_admin_unsupported' => [ 'warning', __( 'Scopes for multisite super admins are not supported.', 'mandate-app-security' ) ],
		];
		if ( !isset( $messages[ $message ] ) ) {
			return $this->hiddenNotice();
		}

		[ $type, $text ] = $messages[ $message ];
		return $this->notice( 'notice notice-'.$type.' is-dismissible', $text );
	}

	/**
	 * @param list<ApplicationPasswordRecord> $passwords
	 * @return AdminNoticeContract
	 */
	private function buildPageNotice( int $selectedUserId, array $passwords, string $selectedUuid ) :array {
		if ( $selectedUserId < 1 ) {
			return $this->hiddenNotice();
		}

		if ( $passwords === [] ) {
			return $this->notice(
				'notice notice-info',
				__( 'The selected user has no application passwords.', 'mandate-app-security' )
			);
		}

		if ( $selectedUuid === '' ) {
			return $this->notice(
				'notice notice-warning',
				__( 'Select an application password before saving a scope.', 'mandate-app-security' )
			);
		}

		return $this->hiddenNotice();
	}

	/**
	 * @param list<ApplicationPasswordRecord> $passwords
	 * @param CapabilityScopeRecord|null $scope
	 * @param list<string> $currentRoleSlugs
	 * @return AdminSelectionFormContract
	 */
	private function buildSelectionForm(
		int $selectedUserId,
		array $passwords,
		string $selectedUuid,
		?array $scope,
		array $currentRoleSlugs,
		bool $isReadOnly
	) :array {
		return [
			'selected_user_id' => $selectedUserId,
			'selected_uuid'    => $selectedUuid,
			'page_slug'        => Plugin::MENU_SLUG,
			'role_summary'     => $this->buildRoleSummary( $currentRoleSlugs ),
			'password_options' => $this->buildPasswordOptions( $passwords, $selectedUuid ),
			'password_summary' => $this->buildPasswordSummary( $passwords, $selectedUuid, $scope, $currentRoleSlugs, $isReadOnly ),
		];
	}

	/**
	 * @param list<string> $roleSlugs
	 * @return AdminRoleSummaryContract
	 */
	private function buildRoleSummary( array $roleSlugs ) :array {
		$rows = $this->roleProvider->roleSummaries( $roleSlugs );
		return [
			'title'      => __( 'Roles for selected user', 'mandate-app-security' ),
			'empty_text' => __( 'No roles assigned.', 'mandate-app-security' ),
			'has_roles'  => $rows !== [],
			'rows'       => $rows,
		];
	}

	/**
	 * @param list<ApplicationPasswordRecord> $passwords
	 * @return list<array{uuid:string,name:string,selected:bool}>
	 */
	private function buildPasswordOptions( array $passwords, string $selectedUuid ) :array {
		$options = [];
		foreach ( $passwords as $password ) {
			$selected = $password[ 'uuid' ] === $selectedUuid;
			$options[] = [
				'uuid'     => $password[ 'uuid' ],
				'name'     => $password[ 'name' ],
				'selected' => $selected,
			];
		}

		return $options;
	}

	/**
	 * @param list<ApplicationPasswordRecord> $passwords
	 * @param CapabilityScopeRecord|null $scope
	 * @param list<string> $currentRoleSlugs
	 * @return AdminPasswordSummaryContract
	 */
	private function buildPasswordSummary(
		array $passwords,
		string $selectedUuid,
		?array $scope,
		array $currentRoleSlugs,
		bool $isReadOnly
	) :array {
		$password = $this->selectedPassword( $passwords, $selectedUuid );
		if ( $password === null ) {
			return [
				'is_visible'   => false,
				'title'        => __( 'Selected Password Info', 'mandate-app-security' ),
				'title_id'     => 'mandate-password-summary-title',
				'container_id' => 'mandate-password-summary',
				'sections'     => [],
				'warnings'     => [],
			];
		}

		$expiresOn = $scope === null ? null : $scope[ 'expires_on' ];
		$sections = [
			[
				'show_divider_before' => false,
				'details'             => [
					$this->textDetail( __( 'Name', 'mandate-app-security' ), $password[ 'name' ] ),
					$this->textDetail( __( 'UUID', 'mandate-app-security' ), $password[ 'uuid' ] ),
					$this->textDetail( __( 'App ID', 'mandate-app-security' ), $password[ 'app_id' ] ),
					$this->textDetail( __( 'Created', 'mandate-app-security' ), $this->formatTimestamp( $password[ 'created' ] ) ),
					$this->textDetail( __( 'Last Used', 'mandate-app-security' ), $this->formatTimestamp( $password[ 'last_used' ] ) ),
				],
			],
			[
				'show_divider_before' => true,
				'details'             => [
					$this->textDetail(
						__( 'Restricted Scope', 'mandate-app-security' ),
						$this->formatRestrictedScope( $scope, $expiresOn )
					),
					$this->expirationDetail( $expiresOn, $isReadOnly ),
				],
			],
		];

		if ( $scope !== null && $scope[ 'admin_locked' ] ) {
			$sections[ 1 ][ 'details' ][] = $this->textDetail(
				__( 'Admin Lock', 'mandate-app-security' ),
				__( 'Locked', 'mandate-app-security' )
			);
		}

		if ( $scope !== null ) {
			$sections[ 1 ][ 'details' ][] = $this->textDetail(
				__( 'Scope Last Saved', 'mandate-app-security' ),
				$this->formatTimestamp( $scope[ 'updated_at' ] )
			);
			$sections[ 1 ][ 'details' ][] = $this->textDetail(
				__( 'Roles When Saved', 'mandate-app-security' ),
				$scope[ 'roles_at_update' ] === null
					? __( 'Not recorded', 'mandate-app-security' )
					: $this->formatRoleSlugs( $scope[ 'roles_at_update' ] )
			);
		}

		$warnings = [];
		if ( $scope !== null && $scope[ 'roles_at_update' ] !== null && $scope[ 'roles_at_update' ] !== $currentRoleSlugs ) {
			$warnings[] = $this->roleSnapshotWarning();
		}

		return [
			'is_visible'   => true,
			'title'        => __( 'Selected Password Info', 'mandate-app-security' ),
			'title_id'     => 'mandate-password-summary-title',
			'container_id' => 'mandate-password-summary',
			'sections'     => $sections,
			'warnings'     => $warnings,
		];
	}

	/**
	 * @return AdminPasswordWarningContract
	 */
	private function roleSnapshotWarning() :array {
		return [
			'classes'              => 'notice notice-warning inline',
			'text'                 => __( 'The selected user roles have changed since this Mandate App Security record was saved. Review the saved restrictions before relying on this Application Password.', 'mandate-app-security' ),
			'role_snapshot_status' => 'changed',
		];
	}

	/**
	 * @return array{kind:'text',label:string,value:string}
	 */
	private function textDetail( string $label, string $value ) :array {
		return [
			'kind'  => 'text',
			'label' => $label,
			'value' => $value === '' ? '-' : $value,
		];
	}

	/**
	 * @return AdminPasswordDetailExpirationContract
	 */
	private function expirationDetail( ?string $expiresOn, bool $disabled ) :array {
		$expired = $this->expirationDatePolicy->isExpired( $expiresOn );
		$state = $expiresOn === null ? 'never' : ( $expired ? 'expired' : 'date' );
		$value = $expiresOn === null
			? __( 'Never expires', 'mandate-app-security' )
			// translators: %s: Application Password expiration date.
			: ( $expired ? sprintf( __( '%s (expired)', 'mandate-app-security' ), $expiresOn ) : $expiresOn );

		return [
			'kind'    => 'expiration',
			'label'   => __( 'Expiration Date', 'mandate-app-security' ),
			'value'   => $value,
			'classes' => 'button-link mandate-expiration-summary'.( $expired ? ' is-expired' : '' ),
			'state'   => $state,
			'disabled' => $disabled,
			'input'   => [
				'id'         => 'mandate-expiration-date',
				'name'       => 'expiration_date',
				'value'      => $expiresOn ?? '',
				'form'       => self::SCOPE_FORM_ID,
				'aria_label' => __( 'Expiration Date', 'mandate-app-security' ),
				'disabled'   => $disabled,
			],
		];
	}

	/**
	 * @param CapabilityScopeRecord|null $scope
	 */
	private function formatRestrictedScope( ?array $scope, ?string $expiresOn ) :string {
		$restrictions = [];
		if ( $scope !== null && $scope[ 'capabilities_restricted' ] ) {
			$restrictions[] = __( 'Capabilities', 'mandate-app-security' );
		}
		if ( $expiresOn !== null ) {
			$restrictions[] = __( 'Expiration date', 'mandate-app-security' );
		}

		return $restrictions === [] ? __( 'Unrestricted', 'mandate-app-security' ) : implode( ' / ', $restrictions );
	}

	/**
	 * @param list<string> $roleSlugs
	 */
	private function formatRoleSlugs( array $roleSlugs ) :string {
		return $roleSlugs === [] ? __( 'No roles', 'mandate-app-security' ) : implode( ', ', $roleSlugs );
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

	/**
	 * @param array{wordpress:array{primitive:array<string,true>,meta:array<string,true>},other:array{primitive:array<string,true>,meta:array<string,true>}} $capabilityGroups
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 * @return AdminScopeFormContract
	 */
	private function buildScopeForm(
		int $selectedUserId,
		string $selectedUuid,
		bool $isSuperAdmin,
		bool $adminLocked,
		bool $isReadOnly,
		bool $canManageAnyScope,
		array $capabilityGroups,
		array $selectedCaps,
		array $selectedMetaCaps
	) :array {
		return [
			'id'                  => self::SCOPE_FORM_ID,
			'user_id'             => $selectedUserId,
			'uuid'                => $selectedUuid,
			'heading'             => __( 'Capability Scope', 'mandate-app-security' ),
			'tablist_label'       => __( 'Capability groups', 'mandate-app-security' ),
			'admin_lock_status'   => $adminLocked ? 'locked' : 'unlocked',
			'super_admin_notice'  => $isSuperAdmin
				? $this->notice( 'notice notice-warning', __( 'Scopes for multisite super admins are not supported.', 'mandate-app-security' ) )
				: $this->hiddenNotice(),
			'lock_notice'         => $adminLocked
				? $this->notice( 'notice notice-info', __( 'This application password scope is locked by an administrator.', 'mandate-app-security' ) )
				: $this->hiddenNotice(),
			'admin_lock'          => $this->adminLockControl( $canManageAnyScope, $adminLocked, $isSuperAdmin ),
			'tabs'                => [
				$this->tab( 'wordpress', __( 'WordPress Capabilities', 'mandate-app-security' ), true ),
				$this->tab( 'other', __( 'Third-Party Capabilities', 'mandate-app-security' ), false ),
			],
			'panels'              => [
				$this->capabilityPanel( 'wordpress', $capabilityGroups[ 'wordpress' ], $selectedCaps, $selectedMetaCaps, $isReadOnly ),
				$this->capabilityPanel( 'other', $capabilityGroups[ 'other' ], $selectedCaps, $selectedMetaCaps, $isReadOnly ),
			],
			'actions'             => [
				$this->scopeAction(
					AdminScopeFormSecurity::ACTION_SAVE,
					__( 'Save Scope', 'mandate-app-security' ),
					'button button-primary',
					$isSuperAdmin || $isReadOnly
				),
				$this->scopeAction(
					AdminScopeFormSecurity::ACTION_CLEAR,
					__( 'Reset to Defaults', 'mandate-app-security' ),
					'button',
					$isReadOnly
				),
			],
		];
	}

	/**
	 * @return array{is_visible:bool,name:string,value:string,label:string,checked:bool,disabled:bool}
	 */
	private function adminLockControl( bool $isVisible, bool $checked, bool $disabled ) :array {
		return [
			'is_visible' => $isVisible,
			'name'       => 'admin_locked',
			'value'      => '1',
			'label'      => __( 'Lock this scope so the application password owner cannot edit it.', 'mandate-app-security' ),
			'checked'    => $checked,
			'disabled'   => $disabled,
		];
	}

	/**
	 * @return array{key:string,id:string,panel_id:string,label:string,classes:string,aria_selected:string}
	 */
	private function tab( string $key, string $label, bool $active ) :array {
		return [
			'key'           => $key,
			'id'            => 'mandate-tab-'.$key,
			'panel_id'      => 'mandate-panel-'.$key,
			'label'         => $label,
			'classes'       => 'nav-tab'.( $active ? ' nav-tab-active' : '' ),
			'aria_selected' => $active ? 'true' : 'false',
		];
	}

	/**
	 * @param array{primitive:array<string,true>,meta:array<string,true>} $capabilities
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 * @return AdminCapabilityPanelContract
	 */
	private function capabilityPanel(
		string $groupKey,
		array $capabilities,
		array $selectedCaps,
		array $selectedMetaCaps,
		bool $isReadOnly
	) :array {
		return [
			'key'          => $groupKey,
			'id'           => 'mandate-panel-'.$groupKey,
			'tab_id'       => 'mandate-tab-'.$groupKey,
			'sections'     => [
				$this->capabilitySection(
					$groupKey,
					'primitive',
					__( 'Role-Derived Primitive Capabilities', 'mandate-app-security' ),
					'allowed_caps',
					$capabilities[ 'primitive' ],
					$selectedCaps,
					$isReadOnly
				),
				$this->capabilitySection(
					$groupKey,
					'meta',
					__( 'Registered Meta Capabilities', 'mandate-app-security' ),
					'allowed_meta_caps',
					$capabilities[ 'meta' ],
					$selectedMetaCaps,
					$isReadOnly
				),
			],
			'bulk_actions' => [
				'select_all'   => [
					'label'    => __( 'Select All', 'mandate-app-security' ),
					'state'    => 'checked',
					'disabled' => $isReadOnly,
				],
				'deselect_all' => [
					'label'    => __( 'Deselect All', 'mandate-app-security' ),
					'state'    => 'unchecked',
					'disabled' => $isReadOnly,
				],
			],
		];
	}

	/**
	 * @param array<string,true> $capabilities
	 * @param array<string,true> $selected
	 * @return AdminCapabilitySectionContract
	 */
	private function capabilitySection(
		string $groupKey,
		string $type,
		string $label,
		string $fieldName,
		array $capabilities,
		array $selected,
		bool $isReadOnly
	) :array {
		$items = [];
		foreach ( array_keys( $capabilities ) as $capability ) {
			$description = $this->descriptionProvider->descriptionFor( $capability );
			$checked = isset( $selected[ $capability ] );
			$items[] = [
				'name'         => $capability,
				'field_name'   => $fieldName,
				'checked'      => $checked,
				'disabled'     => $isReadOnly,
				'has_tooltip'  => $description !== '',
				'tooltip_text' => $description,
			];
		}

		return [
			'id'         => 'mandate-'.$groupKey.'-'.$type.'-capabilities',
			'label'      => $label,
			'empty_text' => __( 'No capabilities are available for this group.', 'mandate-app-security' ),
			'is_empty'   => $items === [],
			'items'      => $items,
		];
	}

	/**
	 * @return array{name:string,value:string,label:string,classes:string,disabled:bool}
	 */
	private function scopeAction( string $value, string $label, string $classes, bool $disabled ) :array {
		return [
			'name'     => 'mandate_action',
			'value'    => $value,
			'label'    => $label,
			'classes'  => $classes,
			'disabled' => $disabled,
		];
	}

	private function selectedUserId() :int {
		return $this->accessPolicy->selectedUserId( $this->getScalar( 'user_id' ) );
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

	private function buildUserDropdown( int $selectedUserId ) :string {
		if ( $this->accessPolicy->shouldRestrictUserSelection() ) {
			return $this->trustedHtmlSanitizer->dropdown(
				sprintf(
					'<select name="user_id" id="mandate-user" disabled="disabled"><option value="%1$s" selected="selected">%2$s</option></select>',
					esc_attr( (string)$selectedUserId ),
					esc_html( $this->userOptionLabel( $selectedUserId ) )
				)
			);
		}

		return $this->trustedHtmlSanitizer->dropdown( (string)wp_dropdown_users(
			[
				'name'     => 'user_id',
				'id'       => 'mandate-user',
				'selected' => $selectedUserId,
				'show'     => 'display_name_with_login',
				'echo'     => false,
			]
		) );
	}

	private function userOptionLabel( int $userId ) :string {
		$user = $userId > 0 ? get_userdata( $userId ) : false;
		if ( is_object( $user ) ) {
			foreach ( [ 'display_name', 'user_login', 'user_nicename' ] as $property ) {
				if ( isset( $user->$property ) && is_scalar( $user->$property ) && (string)$user->$property !== '' ) {
					return (string)$user->$property;
				}
			}
		}

		return (string)$userId;
	}

	private function formatTimestamp( int $timestamp ) :string {
		if ( $timestamp < 1 ) {
			return __( 'Never', 'mandate-app-security' );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sanitized non-mutating admin view-state query arg.
		return $this->requestScalar( $_GET, $key );
	}

	/**
	 * @param array<string,mixed> $source
	 */
	private function requestScalar( array $source, string $key ) :string {
		$value = $source[ $key ] ?? '';
		return is_scalar( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
	}

	/**
	 * @return AdminNoticeContract
	 */
	private function hiddenNotice() :array {
		return [
			'is_visible' => false,
			'classes'    => '',
			'text'       => '',
		];
	}

	/**
	 * @return AdminNoticeContract
	 */
	private function notice( string $classes, string $text ) :array {
		return [
			'is_visible' => true,
			'classes'    => $classes,
			'text'       => $text,
		];
	}
}
