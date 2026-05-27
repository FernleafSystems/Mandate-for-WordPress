<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Admin;

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityName;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\Mandate\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\Mandate\Plugin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPage {

	private const ASSET_HANDLE = 'mandate-admin-page';
	public const MESSAGE_QUERY_KEY = 'mandate_app_security_message';

	private ScopeRepository $scopeRepository;

	private ApplicationPasswordRepository $passwordRepository;

	private CapabilityCandidateProvider $candidateProvider;

	private MetaCapabilityRegistry $metaRegistry;

	private string $pluginFile;

	private ExpirationDatePolicy $expirationDatePolicy;

	private AdminUserRoleProvider $roleProvider;

	private AdminScopeFormSecurity $formSecurity;

	private AdminPageViewDataBuilder $viewDataBuilder;

	private AdminTemplateRenderer $templateRenderer;

	private AdminScopeAccessPolicy $accessPolicy;

	public function __construct(
		ScopeRepository $scopeRepository,
		ApplicationPasswordRepository $passwordRepository,
		CapabilityCandidateProvider $candidateProvider,
		MetaCapabilityRegistry $metaRegistry,
		string $pluginFile,
		ExpirationDatePolicy $expirationDatePolicy,
		AdminUserRoleProvider $roleProvider,
		AdminScopeFormSecurity $formSecurity,
		AdminPageViewDataBuilder $viewDataBuilder,
		AdminTemplateRenderer $templateRenderer,
		?AdminScopeAccessPolicy $accessPolicy = null
	) {
		$this->scopeRepository = $scopeRepository;
		$this->passwordRepository = $passwordRepository;
		$this->candidateProvider = $candidateProvider;
		$this->metaRegistry = $metaRegistry;
		$this->pluginFile = $pluginFile;
		$this->expirationDatePolicy = $expirationDatePolicy;
		$this->roleProvider = $roleProvider;
		$this->formSecurity = $formSecurity;
		$this->viewDataBuilder = $viewDataBuilder;
		$this->templateRenderer = $templateRenderer;
		$this->accessPolicy = $accessPolicy ?? new AdminScopeAccessPolicy();
	}

	public function enqueueAssets( string $hookSuffix ) :void {
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
		$hasFormAction = isset( $_POST[ AdminScopeFormSecurity::ACTION_FIELD ] );
		if ( !$hasFormAction ) {
			return;
		}

		$this->requirePageAccess();

		$userId = absint( $this->postScalar( 'user_id' ) );
		$uuid = ApplicationPasswordRepository::normalizeUuid( $this->postScalar( 'app_password_uuid' ) );
		$action = sanitize_key( $this->postScalar( AdminScopeFormSecurity::ACTION_FIELD ) );
		$message = 'invalid';

		if ( !$this->formSecurity->isSupportedAction( $action ) ) {
			$this->redirectAfterPost( $userId, $uuid, $message );
		}

		check_admin_referer(
			$this->formSecurity->nonceAction( $action, $userId, $uuid ),
			$this->formSecurity->nonceName( $action )
		);

		$scope = $uuid === '' ? null : $this->scopeRepository->findForUser( $userId, $uuid );
		if ( $userId > 0 && $uuid !== '' && $this->passwordRepository->userOwnsPassword( $userId, $uuid ) ) {
			if ( !$this->accessPolicy->canManageUserScope( $userId ) ) {
				$message = 'invalid';
			}
			elseif ( !$this->accessPolicy->canMutateScope( $userId, $scope ) ) {
				$message = 'locked';
			}
			elseif ( $action === AdminScopeFormSecurity::ACTION_CLEAR ) {
				$message = $this->scopeRepository->deleteForUser( $userId, $uuid ) ? 'reset' : 'invalid';
			}
			elseif ( $action === AdminScopeFormSecurity::ACTION_SAVE ) {
				$message = $this->handleSaveScopePost( $userId, $uuid, $scope );
			}
		}

		$this->redirectAfterPost( $userId, $uuid, $message );
	}

	/**
	 * @param array{admin_locked:bool}|null $scope
	 */
	private function handleSaveScopePost( int $userId, string $uuid, ?array $scope ) :string {
		if ( $this->isSuperAdminUser( $userId ) ) {
			return 'super_admin_unsupported';
		}

		$candidates = $this->candidateProvider->forUser( $userId );
		$submittedCaps = $this->verifiedPostScalarList( 'allowed_caps' );
		$submittedMetaCaps = $this->verifiedPostScalarList( 'allowed_meta_caps' );
		$expiresOn = $this->postedExpirationDate();

		if ( $expiresOn === false ) {
			return 'invalid';
		}

		$allowedCaps = array_intersect_key( CapabilityName::normalizeMap( $submittedCaps ), $candidates );
		$allowedMetaCaps = $this->metaRegistry->intersectSubmitted( $submittedMetaCaps );
		$capabilitiesRestricted = !( $allowedCaps === $candidates && $allowedMetaCaps === $this->metaRegistry->registered() );
		$canAdminLockScope = $this->accessPolicy->canAdminLockScopeForCaps( $candidates );
		$adminLocked = $canAdminLockScope
			&& (
				$this->accessPolicy->canManageAnyScope()
					? $this->postedCheckbox( 'admin_locked' )
					: ( $scope !== null && $scope[ 'admin_locked' ] )
			);
		if ( !$capabilitiesRestricted && $expiresOn === null && !$adminLocked ) {
			return $this->scopeRepository->deleteForUser( $userId, $uuid ) ? 'reset' : 'invalid';
		}

		return $this->scopeRepository->save(
			$uuid,
			$userId,
			$allowedCaps,
			$allowedMetaCaps,
			$this->roleProvider->roleSlugsForUser( $userId ),
			get_current_user_id(),
			$expiresOn,
			$capabilitiesRestricted,
			$adminLocked
		) ? 'saved' : 'invalid';
	}

	private function redirectAfterPost( int $userId, string $uuid, string $message ) :void {
		wp_safe_redirect(
			add_query_arg(
				[
					'page'              => Plugin::MENU_SLUG,
					'user_id'           => $userId,
					'app_password_uuid' => $uuid,
					self::MESSAGE_QUERY_KEY => $message,
				],
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	private function requirePageAccess() :void {
		if ( !$this->accessPolicy->canAccessPage() ) {
			wp_die( esc_html__( 'You do not have permission to manage application password scopes.', 'mandate-app-security' ) );
		}
	}

	public function render() :void {
		$this->requirePageAccess();
		$html = $this->templateRenderer->render( 'admin-page.php', $this->viewDataBuilder->build() );
		echo wp_kses( $html, $this->templateRenderer->allowedAdminHtml() );
	}

	private function isSuperAdminUser( int $userId ) :bool {
		return function_exists( 'is_multisite' )
			&& is_multisite()
			&& function_exists( 'is_super_admin' )
			&& is_super_admin( $userId );
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

	private function postedCheckbox( string $key ) :bool {
		return $this->postScalar( $key ) !== '';
	}

	/**
	 * @return string[]
	 */
	private function verifiedPostScalarList( string $key ) :array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in handlePost(); each list item is sanitized below.
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
