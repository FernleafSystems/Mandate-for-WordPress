<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin;

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityDescriptionProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Plugin;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\PluginIdentity;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-import-type ApplicationPasswordRecord from ApplicationPasswordRepository
 * @phpstan-import-type CapabilityScopeRecord from ScopeRepository
 * @phpstan-import-type CapabilityGroupingResult from CapabilityGroupProvider
 * @phpstan-import-type CapabilityGroupItem from CapabilityGroupProvider
 * @phpstan-import-type CapabilityGroupSection from CapabilityGroupProvider
 * @phpstan-import-type CapabilitySourceGroup from CapabilityGroupProvider
 * @phpstan-import-type AdminPageRenderData from AdminPageRenderContracts
 * @phpstan-import-type AdminNoticeContract from AdminPageRenderContracts
 * @phpstan-import-type AdminCapabilityPanelContract from AdminPageRenderContracts
 * @phpstan-import-type AdminCapabilitySectionContract from AdminPageRenderContracts
 * @phpstan-import-type AdminPageStringsContract from AdminPageRenderContracts
 * @phpstan-import-type AdminRoleSummaryContract from AdminPageRenderContracts
 * @phpstan-import-type AdminSelectionFormContract from AdminPageRenderContracts
 * @phpstan-import-type AdminSummaryContract from AdminPageRenderContracts
 * @phpstan-import-type AdminSummaryWarningContract from AdminPageRenderContracts
 * @phpstan-import-type AdminSummaryDetailTextContract from AdminPageRenderContracts
 * @phpstan-import-type AdminSummaryDetailExpirationContract from AdminPageRenderContracts
 * @phpstan-import-type AdminSummaryDetailAdminLockContract from AdminPageRenderContracts
 * @phpstan-import-type AdminScopeFormContract from AdminPageRenderContracts
 */
class AdminPageViewDataBuilder {

	private const SCOPE_FORM_ID = PluginIdentity::HTML_PREFIX.'scope-form';
	private const PASSWORD_INFO_TITLE_ID = PluginIdentity::HTML_PREFIX.'password-info-title';
	private const PASSWORD_INFO_CONTAINER_ID = PluginIdentity::HTML_PREFIX.'password-info';
	private const SCOPE_SUMMARY_TITLE_ID = PluginIdentity::HTML_PREFIX.'scope-summary-title';
	private const SCOPE_SUMMARY_CONTAINER_ID = PluginIdentity::HTML_PREFIX.'scope-summary';
	private const ADMIN_LOCK_INPUT_ID = PluginIdentity::HTML_PREFIX.'admin-locked';
	private const EXPIRATION_INPUT_ID = PluginIdentity::HTML_PREFIX.'expiration-date';
	private const USER_SELECT_ID = PluginIdentity::HTML_PREFIX.'user';
	private const DEFAULT_CAPABILITY_SOURCE = CapabilityGroupProvider::SOURCE_WORDPRESS;
	private const DEFAULT_CAPABILITY_GROUPING = CapabilityGroupProvider::MODE_AREA;

	private AdminRequest $request;

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
		AdminRequest $request,
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
		$this->request = $request;
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
		$canAdminLockScope = $this->accessPolicy->canAdminLockScopeForCaps( $candidateCaps );
		$adminLocked = $scope !== null && $scope[ 'admin_locked' ] && $canAdminLockScope;

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
				'root' => 'wrap '.PluginIdentity::RUNTIME_PREFIX,
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
					$adminLocked,
					$isReadOnly,
					$canManageAnyScope,
					$isSuperAdmin || !$canAdminLockScope
				),
				'scope_form'     => $this->buildScopeForm(
					$selectedUserId,
					$selectedUuid,
					$isSuperAdmin,
					$adminLocked,
					$isReadOnly,
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
		$message = sanitize_key( $this->request->getScalar( AdminPage::MESSAGE_QUERY_KEY ) );
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
		bool $adminLocked,
		bool $isReadOnly,
		bool $canManageAnyScope,
		bool $adminLockDisabled
	) :array {
		$selectedPassword = $this->selectedPassword( $passwords, $selectedUuid );

		return [
			'selected_user_id' => $selectedUserId,
			'selected_uuid'    => $selectedUuid,
			'page_slug'        => Plugin::MENU_SLUG,
			'role_summary'     => $this->buildRoleSummary( $currentRoleSlugs ),
			'password_options' => $this->buildPasswordOptions( $passwords, $selectedUuid ),
			'password_info'    => $this->buildPasswordInfoSummary( $selectedPassword ),
			'scope_summary'    => $this->buildScopeSummary(
				$selectedPassword,
				$scope,
				$currentRoleSlugs,
				$adminLocked,
				$isReadOnly,
				$canManageAnyScope,
				$adminLockDisabled
			),
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
	 * @param ApplicationPasswordRecord|null $password
	 * @return AdminSummaryContract
	 */
	private function buildPasswordInfoSummary( ?array $password ) :array {
		if ( $password === null ) {
			return [
				'is_visible'      => false,
				'title'           => __( 'Selected Password Info', 'mandate-app-security' ),
				'title_id'        => self::PASSWORD_INFO_TITLE_ID,
				'title_placement' => 'inside',
				'container_id'    => self::PASSWORD_INFO_CONTAINER_ID,
				'details'         => [],
				'warnings'        => [],
			];
		}

		return [
			'is_visible'      => true,
			'title'           => __( 'Selected Password Info', 'mandate-app-security' ),
			'title_id'        => self::PASSWORD_INFO_TITLE_ID,
			'title_placement' => 'inside',
			'container_id'    => self::PASSWORD_INFO_CONTAINER_ID,
			'details'         => [
				$this->textDetail( __( 'UUID', 'mandate-app-security' ), $password[ 'uuid' ] ),
				$this->textDetail( __( 'Created', 'mandate-app-security' ), $this->formatTimestamp( $password[ 'created' ] ) ),
				$this->textDetail( __( 'Last Used', 'mandate-app-security' ), $this->formatTimestamp( $password[ 'last_used' ] ) ),
			],
			'warnings'        => [],
		];
	}

	/**
	 * @param ApplicationPasswordRecord|null $password
	 * @param CapabilityScopeRecord|null $scope
	 * @param list<string> $currentRoleSlugs
	 * @return AdminSummaryContract
	 */
	private function buildScopeSummary(
		?array $password,
		?array $scope,
		array $currentRoleSlugs,
		bool $adminLocked,
		bool $isReadOnly,
		bool $canManageAnyScope,
		bool $adminLockDisabled
	) :array {
		if ( $password === null ) {
			return [
				'is_visible'      => false,
				'title'           => __( 'Scope Summary', 'mandate-app-security' ),
				'title_id'        => self::SCOPE_SUMMARY_TITLE_ID,
				'title_placement' => 'outside',
				'container_id'    => self::SCOPE_SUMMARY_CONTAINER_ID,
				'details'         => [],
				'warnings'        => [],
			];
		}

		$expiresOn = $scope === null ? null : $scope[ 'expires_on' ];
		$details = [
			$this->textDetail(
				__( 'Restricted Scope', 'mandate-app-security' ),
				$this->formatRestrictedScope( $scope, $expiresOn )
			),
			$this->expirationDetail( $expiresOn, $isReadOnly ),
		];
		$adminLockDetail = $this->adminLockDetail( $adminLocked, $canManageAnyScope, $adminLockDisabled );
		if ( $adminLockDetail !== null ) {
			$details[] = $adminLockDetail;
		}

		if ( $scope !== null ) {
			$details[] = $this->textDetail(
				__( 'Scope Last Saved', 'mandate-app-security' ),
				$this->formatTimestamp( $scope[ 'updated_at' ] )
			);
			$details[] = $this->textDetail(
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
			'is_visible'      => true,
			'title'           => __( 'Scope Summary', 'mandate-app-security' ),
			'title_id'        => self::SCOPE_SUMMARY_TITLE_ID,
			'title_placement' => 'outside',
			'container_id'    => self::SCOPE_SUMMARY_CONTAINER_ID,
			'details'         => $details,
			'warnings'        => $warnings,
		];
	}

	/**
	 * @return AdminSummaryWarningContract
	 */
	private function roleSnapshotWarning() :array {
		return [
			'classes'              => 'notice notice-warning inline',
			'text'                 => __( 'The selected user roles have changed since this Mandate App Security record was saved. Review the saved restrictions before relying on this Application Password.', 'mandate-app-security' ),
			'role_snapshot_status' => 'changed',
		];
	}

	/**
	 * @return AdminSummaryDetailAdminLockContract|AdminSummaryDetailTextContract|null
	 */
	private function adminLockDetail( bool $checked, bool $canManageAnyScope, bool $disabled ) :?array {
		if ( !$canManageAnyScope ) {
			return $checked
				? $this->textDetail( __( 'Lock This Scope', 'mandate-app-security' ), __( 'Locked', 'mandate-app-security' ) )
				: null;
		}

		return [
			'kind'      => 'admin_lock',
			'label'     => __( 'Lock This Scope', 'mandate-app-security' ),
			'help_text' => __( 'Prevent the application password owner from editing or resetting this scope.', 'mandate-app-security' ),
			'input'     => [
				'id'       => self::ADMIN_LOCK_INPUT_ID,
				'name'     => 'admin_locked',
				'value'    => '1',
				'form'     => self::SCOPE_FORM_ID,
				'checked'  => $checked,
				'disabled' => $disabled,
			],
		];
	}

	/**
	 * @return AdminSummaryDetailTextContract
	 */
	private function textDetail( string $label, string $value ) :array {
		return [
			'kind'  => 'text',
			'label' => $label,
			'value' => $value === '' ? '-' : $value,
		];
	}

	/**
	 * @return AdminSummaryDetailExpirationContract
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
			'classes' => 'button-link '.PluginIdentity::htmlIdentifier( 'expiration-summary' ).( $expired ? ' is-expired' : '' ),
			'state'   => $state,
			'disabled' => $disabled,
			'input'   => [
				'id'         => self::EXPIRATION_INPUT_ID,
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
	 * @param CapabilityGroupingResult $capabilityGroups
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
		array $capabilityGroups,
		array $selectedCaps,
		array $selectedMetaCaps
	) :array {
		return [
			'id'                  => self::SCOPE_FORM_ID,
			'user_id'             => $selectedUserId,
			'uuid'                => $selectedUuid,
			'heading'             => __( 'Capability Scope', 'mandate-app-security' ),
			'admin_lock_status'   => $adminLocked ? 'locked' : 'unlocked',
			'super_admin_notice'  => $isSuperAdmin
				? $this->notice( 'notice notice-warning', __( 'Scopes for multisite super admins are not supported.', 'mandate-app-security' ) )
				: $this->hiddenNotice(),
			'lock_notice'         => $adminLocked
				? $this->notice( 'notice notice-info', __( 'This application password scope is locked by an administrator.', 'mandate-app-security' ) )
				: $this->hiddenNotice(),
			'grouping'            => $this->capabilityGrouping( $capabilityGroups, $isReadOnly ),
			'source_tabs'         => $this->capabilitySourceTabs( $capabilityGroups[ 'sources' ] ),
			'source_panels'       => $this->capabilitySourcePanels(
				$capabilityGroups[ 'sources' ],
				$selectedCaps,
				$selectedMetaCaps,
				$isReadOnly,
				self::DEFAULT_CAPABILITY_GROUPING
			),
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
	 * @param CapabilityGroupingResult $capabilityGroups
	 * @return array{label:string,default_source:'wordpress',default_mode:'area',config_json:string,modes:list<array{key:'area'|'action',label:string,checked:bool}>}
	 */
	private function capabilityGrouping( array $capabilityGroups, bool $isReadOnly ) :array {
		return [
			'label'          => __( 'Group capabilities by', 'mandate-app-security' ),
			'default_source' => self::DEFAULT_CAPABILITY_SOURCE,
			'default_mode'   => self::DEFAULT_CAPABILITY_GROUPING,
			'config_json'    => $this->capabilityGroupingConfigJson( $capabilityGroups, $isReadOnly ),
			'modes'          => [
				[
					'key'     => CapabilityGroupProvider::MODE_AREA,
					'label'   => __( 'Area', 'mandate-app-security' ),
					'checked' => true,
				],
				[
					'key'     => CapabilityGroupProvider::MODE_ACTION,
					'label'   => __( 'Action', 'mandate-app-security' ),
					'checked' => false,
				],
			],
		];
	}

	/**
	 * @param CapabilityGroupingResult $capabilityGroups
	 */
	private function capabilityGroupingConfigJson( array $capabilityGroups, bool $isReadOnly ) :string {
		return json_encode(
			[
				'defaultSource' => self::DEFAULT_CAPABILITY_SOURCE,
				'defaultMode'   => self::DEFAULT_CAPABILITY_GROUPING,
				'sources'       => $this->capabilitySourceConfig( $capabilityGroups[ 'sources' ], $isReadOnly ),
			],
			\JSON_THROW_ON_ERROR
		);
	}

	/**
	 * @return array{select_all:array{label:string,state:'checked',disabled:bool},deselect_all:array{label:string,state:'unchecked',disabled:bool}}
	 */
	private function capabilityBulkActions( bool $isReadOnly ) :array {
		return [
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
		];
	}

	/**
	 * @param list<CapabilitySourceGroup> $sources
	 * @return list<array{key:string,emptyText:string,modes:array{area:array{sections:list<array{id:string,key:string,label:string,count:int,itemKeys:list<string>,bulk_actions:array{select_all:array{label:string,state:'checked',disabled:bool},deselect_all:array{label:string,state:'unchecked',disabled:bool}}}>},action:array{sections:list<array{id:string,key:string,label:string,count:int,itemKeys:list<string>,bulk_actions:array{select_all:array{label:string,state:'checked',disabled:bool},deselect_all:array{label:string,state:'unchecked',disabled:bool}}>}}}>
	 */
	private function capabilitySourceConfig( array $sources, bool $isReadOnly ) :array {
		$config = [];
		foreach ( $sources as $source ) {
			$config[] = [
				'key'       => $source[ 'key' ],
				'emptyText' => __( 'No capabilities are available in this source.', 'mandate-app-security' ),
				'modes'     => [
					CapabilityGroupProvider::MODE_AREA   => [
						'sections' => $this->capabilitySectionConfig(
							$source[ CapabilityGroupProvider::MODE_AREA ],
							$source[ 'key' ],
							CapabilityGroupProvider::MODE_AREA,
							$isReadOnly
						),
					],
					CapabilityGroupProvider::MODE_ACTION => [
						'sections' => $this->capabilitySectionConfig(
							$source[ CapabilityGroupProvider::MODE_ACTION ],
							$source[ 'key' ],
							CapabilityGroupProvider::MODE_ACTION,
							$isReadOnly
						),
					],
				],
			];
		}

		return $config;
	}

	/**
	 * @param list<CapabilityGroupSection> $sections
	 * @return list<array{id:string,key:string,label:string,count:int,itemKeys:list<string>,bulk_actions:array{select_all:array{label:string,state:'checked',disabled:bool},deselect_all:array{label:string,state:'unchecked',disabled:bool}}}>
	 */
	private function capabilitySectionConfig( array $sections, string $sourceKey, string $mode, bool $isReadOnly ) :array {
		$config = [];
		foreach ( $sections as $section ) {
			$config[] = [
				'id'       => $this->capabilitySectionId( $sourceKey, $mode, $section[ 'key' ] ),
				'key'      => $section[ 'key' ],
				'label'    => $mode === CapabilityGroupProvider::MODE_AREA
					? $this->areaLabel( $section[ 'key' ] )
					: $this->actionLabel( $section[ 'key' ] ),
				'count'    => count( $section[ 'items' ] ),
				'itemKeys' => array_map(
					fn( array $item ) :string => $this->capabilityItemKey( $item[ 'type' ], $item[ 'name' ] ),
					$section[ 'items' ]
				),
				'bulk_actions' => $this->capabilityBulkActions( $isReadOnly ),
			];
		}

		return $config;
	}

	/**
	 * @param list<CapabilitySourceGroup> $sources
	 * @return list<array{key:string,id:string,panel_id:string,label:string,count:int,selected:bool}>
	 */
	private function capabilitySourceTabs( array $sources ) :array {
		$tabs = [];
		foreach ( $sources as $source ) {
			$tabs[] = [
				'key'      => $source[ 'key' ],
				'id'       => $this->capabilitySourceTabId( $source[ 'key' ] ),
				'panel_id' => $this->capabilitySourcePanelId( $source[ 'key' ] ),
				'label'    => $this->sourceLabel( $source[ 'key' ] ),
				'count'    => count( $source[ 'items' ] ),
				'selected' => $source[ 'key' ] === self::DEFAULT_CAPABILITY_SOURCE,
			];
		}

		return $tabs;
	}

	/**
	 * @param list<CapabilitySourceGroup> $sources
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 * @return list<AdminCapabilityPanelContract>
	 */
	private function capabilitySourcePanels(
		array $sources,
		array $selectedCaps,
		array $selectedMetaCaps,
		bool $isReadOnly,
		string $mode
	) :array {
		$panels = [];
		foreach ( $sources as $source ) {
			$sections = [];
			foreach ( $source[ $mode ] as $section ) {
				$sections[] = $this->capabilitySection(
					$source[ 'key' ],
					$mode,
					$section[ 'key' ],
					$mode === CapabilityGroupProvider::MODE_AREA
						? $this->areaLabel( $section[ 'key' ] )
						: $this->actionLabel( $section[ 'key' ] ),
					$section[ 'items' ],
					$selectedCaps,
					$selectedMetaCaps,
					$isReadOnly
				);
			}

			$panels[] = [
				'key'          => $source[ 'key' ],
				'id'           => $this->capabilitySourcePanelId( $source[ 'key' ] ),
				'tab_id'       => $this->capabilitySourceTabId( $source[ 'key' ] ),
				'empty_text'   => __( 'No capabilities are available in this source.', 'mandate-app-security' ),
				'is_empty'     => $source[ 'items' ] === [],
				'section_index' => $this->capabilitySectionIndex( $sections ),
				'sections'     => $sections,
				'bulk_actions' => $this->capabilityBulkActions( $isReadOnly ),
			];
		}

		return $panels;
	}

	/**
	 * @param list<CapabilityGroupItem> $capabilities
	 * @param array<string,true> $selectedCaps
	 * @param array<string,true> $selectedMetaCaps
	 * @return AdminCapabilitySectionContract
	 */
	private function capabilitySection(
		string $sourceKey,
		string $mode,
		string $sectionKey,
		string $label,
		array $capabilities,
		array $selectedCaps,
		array $selectedMetaCaps,
		bool $isReadOnly
	) :array {
		$items = [];
		foreach ( $capabilities as $capability ) {
			$name = $capability[ 'name' ];
			$itemKey = $this->capabilityItemKey( $capability[ 'type' ], $name );
			$description = $this->descriptionProvider->descriptionFor( $name );
			$checked = $capability[ 'type' ] === 'primitive'
				? isset( $selectedCaps[ $name ] )
				: isset( $selectedMetaCaps[ $name ] );
			$items[] = [
				'item_key'            => $itemKey,
				'input_id'            => $this->capabilityInputId( $itemKey ),
				'name'                => $name,
				'type'                => $capability[ 'type' ],
				'field_name'          => $capability[ 'type' ] === 'primitive' ? 'allowed_caps' : 'allowed_meta_caps',
				'checked'             => $checked,
				'disabled'            => $isReadOnly,
				'source'              => $capability[ 'source' ],
				'area'                => $capability[ 'area' ],
				'action'              => $capability[ 'action' ],
				'action_label'        => $this->actionLabel( $capability[ 'action' ] ),
				'action_abbreviation' => $this->actionAbbreviation( $capability[ 'action' ] ),
				'has_tooltip'         => $description !== '',
				'tooltip_text'        => $description,
				'tooltip_aria_label'  => sprintf(
					/* translators: %s: capability name. */
					__( 'More information about %s', 'mandate-app-security' ),
					$name
				),
			];
		}

		return [
			'id'           => $this->capabilitySectionId( $sourceKey, $mode, $sectionKey ),
			'label'        => $label,
			'count'        => count( $items ),
			'items'        => $items,
			'bulk_actions' => $this->capabilityBulkActions( $isReadOnly ),
		];
	}

	/**
	 * @param list<AdminCapabilitySectionContract> $sections
	 * @return list<array{target_id:string,label:string,count:int}>
	 */
	private function capabilitySectionIndex( array $sections ) :array {
		return array_map(
			static fn( array $section ) :array => [
				'target_id' => $section[ 'id' ],
				'label'     => $section[ 'label' ],
				'count'     => $section[ 'count' ],
			],
			$sections
		);
	}

	private function capabilitySectionId( string $source, string $mode, string $section ) :string {
		return PluginIdentity::htmlIdentifier( $source.'-'.$mode.'-'.$section.'-capabilities' );
	}

	private function capabilityItemKey( string $type, string $name ) :string {
		return $type.':'.$name;
	}

	private function capabilityInputId( string $itemKey ) :string {
		return PluginIdentity::htmlIdentifier( 'capability-'.str_replace( ':', '-', $itemKey ) );
	}

	private function capabilitySourcePanelId( string $source ) :string {
		return PluginIdentity::htmlIdentifier( 'capability-source-'.$source );
	}

	private function capabilitySourceTabId( string $source ) :string {
		return PluginIdentity::htmlIdentifier( 'capability-source-tab-'.$source );
	}

	private function sourceLabel( string $source ) :string {
		return match ( $source ) {
			CapabilityGroupProvider::SOURCE_WORDPRESS   => __( 'WordPress', 'mandate-app-security' ),
			CapabilityGroupProvider::SOURCE_THIRD_PARTY => __( 'Third-party', 'mandate-app-security' ),
		};
	}

	private function areaLabel( string $area ) :string {
		return match ( $area ) {
			CapabilityGroupProvider::AREA_POSTS       => __( 'Posts', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_PAGES       => __( 'Pages', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_MEDIA       => __( 'Media', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_TAXONOMY    => __( 'Taxonomy', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_COMMENTS    => __( 'Comments', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_USERS       => __( 'Users', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_PLUGINS     => __( 'Plugins', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_THEMES      => __( 'Themes', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_GENERAL     => __( 'General', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_NETWORK     => __( 'Network / Sites', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_PRIVACY     => __( 'Privacy', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_UPDATES     => __( 'Core / Updates', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_LEGACY      => __( 'Legacy', 'mandate-app-security' ),
			CapabilityGroupProvider::AREA_THIRD_PARTY => __( 'Third-party / Other', 'mandate-app-security' ),
		};
	}

	private function actionLabel( string $action ) :string {
		return match ( $action ) {
			CapabilityGroupProvider::ACTION_READ   => __( 'Read', 'mandate-app-security' ),
			CapabilityGroupProvider::ACTION_WRITE  => __( 'Write', 'mandate-app-security' ),
			CapabilityGroupProvider::ACTION_DELETE => __( 'Delete', 'mandate-app-security' ),
		};
	}

	private function actionAbbreviation( string $action ) :string {
		return match ( $action ) {
			CapabilityGroupProvider::ACTION_READ   => 'R',
			CapabilityGroupProvider::ACTION_WRITE  => 'W',
			CapabilityGroupProvider::ACTION_DELETE => 'D',
		};
	}

	/**
	 * @return array{name:string,value:string,label:string,classes:string,disabled:bool}
	 */
	private function scopeAction( string $value, string $label, string $classes, bool $disabled ) :array {
		return [
			'name'     => AdminScopeFormSecurity::ACTION_FIELD,
			'value'    => $value,
			'label'    => $label,
			'classes'  => $classes,
			'disabled' => $disabled,
		];
	}

	private function selectedUserId() :int {
		return $this->accessPolicy->selectedUserId( $this->request->getScalar( 'user_id' ) );
	}

	/**
	 * @param list<ApplicationPasswordRecord> $passwords
	 */
	private function selectedPasswordUuid( array $passwords ) :string {
		if ( $passwords === [] ) {
			return '';
		}

		$requested = ApplicationPasswordRepository::normalizeUuid( $this->request->getScalar( 'app_password_uuid' ) );
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
					'<select name="user_id" id="%3$s" disabled="disabled"><option value="%1$s" selected="selected">%2$s</option></select>',
					esc_attr( (string)$selectedUserId ),
					esc_html( $this->userOptionLabel( $selectedUserId ) ),
					esc_attr( self::USER_SELECT_ID )
				)
			);
		}

		return $this->trustedHtmlSanitizer->dropdown( (string)wp_dropdown_users(
			[
				'name'     => 'user_id',
				'id'       => self::USER_SELECT_ID,
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

		return wp_date( 'Y-m-d H:i:s', $timestamp );
	}

	private function isSuperAdminUser( int $userId ) :bool {
		return is_multisite() && is_super_admin( $userId );
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
