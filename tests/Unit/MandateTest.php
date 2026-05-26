<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPageViewDataBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminScopeFormSecurity;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminTemplateRenderer;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminTrustedHtmlSanitizer;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminUserRoleProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityDescriptionProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\Mandate\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Plugin;
use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;

final class MandateTest extends Wpm_Test_Case {

	private const UUID = '11111111-1111-4111-8111-111111111111';
	private const OTHER_UUID = '22222222-2222-4222-8222-222222222222';

	public function testScopeNormalizationStoresBooleanMaps() :void {
		$record = $this->scopeRepository()->normalizeRecord(
			[
				'user_id'           => '12',
				'allowed_caps'      => [
					'read'         => true,
					'edit_posts'   => 1,
					'delete_posts' => false,
					'Bad Cap'      => true,
				],
				'allowed_meta_caps' => [
					'edit_post' => true,
					'bad meta'  => true,
				],
				'updated_at'        => '10',
				'updated_by'        => '3',
			]
		);

		$this->assertSame(
			[
				'badcap'     => true,
				'edit_posts' => true,
				'read'       => true,
			],
			$record[ 'allowed_caps' ]
		);
		$this->assertSame(
			[
				'badmeta'   => true,
				'edit_post' => true,
			],
			$record[ 'allowed_meta_caps' ]
		);
		$this->assertSame( 12, $record[ 'user_id' ] );
		$this->assertSame( null, $record[ 'roles_at_update' ] );
		$this->assertSame( 10, $record[ 'updated_at' ] );
		$this->assertSame( 3, $record[ 'updated_by' ] );
	}

	public function testScopeNormalizationStoresRoleSlugSnapshot() :void {
		$repository = $this->scopeRepository();
		$record = $repository->normalizeRecord(
			[
				'user_id'           => 12,
				'allowed_caps'      => [],
				'allowed_meta_caps' => [],
				'roles_at_update'   => [ 'wpm_editor', 'WPM_ADMIN', '', 'shop-manager', 'wpm_editor', [] ],
			]
		);

		$this->assertSame( [ 'shop-manager', 'wpm_admin', 'wpm_editor' ], $record[ 'roles_at_update' ] );

		$record = $repository->normalizeRecord(
			[
				'user_id'           => 12,
				'allowed_caps'      => [],
				'allowed_meta_caps' => [],
				'roles_at_update'   => 'wpm_editor',
			]
		);

		$this->assertSame( null, $record[ 'roles_at_update' ] );
	}

	public function testPluginOptionsRepositoryStoresVersionedDocumentContract() :void {
		$repository = $this->scopeRepository();

		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );

		$stored = $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] ?? null;
		$this->assertTrue( is_array( $stored ) );
		$metadata = $stored[ 'metadata' ];
		$this->assertSame( PluginOptionsRepository::CURRENT_SCHEMA_VERSION, $metadata[ 'schema_version' ] );
		$this->assertSame( Plugin::VERSION, $metadata[ 'plugin_version' ] );
		$this->assertTrue( $metadata[ 'created_at' ] > 0 );
		$this->assertSame( $metadata[ 'created_at' ], $metadata[ 'updated_at' ] );
		$this->assertArrayHasKey( self::UUID, $stored[ 'scopes' ] );
		$this->assertSame( false, $GLOBALS[ 'wpm_test_autoload' ][ PluginOptionsRepository::OPTION_NAME ] );
	}

	public function testPluginOptionsRepositoryPreservesCreatedAtAndRefreshesUpdatedAt() :void {
		$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] = [
			'metadata' => [
				'schema_version' => PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
				'plugin_version' => '0.0.1',
				'created_at'     => 100,
				'updated_at'     => 200,
			],
			'scopes'   => [],
		];

		$this->scopeRepository()->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );

		$metadata = $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ][ 'metadata' ];
		$this->assertSame( 100, $metadata[ 'created_at' ] );
		$this->assertTrue( $metadata[ 'updated_at' ] > 200 );
		$this->assertSame( Plugin::VERSION, $metadata[ 'plugin_version' ] );
		$this->assertSame( false, $GLOBALS[ 'wpm_test_autoload' ][ PluginOptionsRepository::OPTION_NAME ] );
	}

	public function testPluginOptionsRepositoryFailsOpenForMalformedStoredDocumentsWithoutReadMutation() :void {
		$validMetadata = [
			'schema_version' => PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
			'plugin_version' => Plugin::VERSION,
			'created_at'     => 100,
			'updated_at'     => 100,
		];
		$validScope = [
			'user_id'           => 5,
			'allowed_caps'      => [ 'read' => true ],
			'allowed_meta_caps' => [],
			'updated_at'        => 100,
			'updated_by'        => 1,
		];
		$cases = [
			'scalar'                => 'bad',
			'missing_metadata'      => [ 'scopes' => [] ],
			'missing_scopes'        => [ 'metadata' => $validMetadata ],
			'future_schema'         => [
				'metadata' => [ 'schema_version' => 99, 'plugin_version' => Plugin::VERSION, 'created_at' => 100, 'updated_at' => 100 ],
				'scopes'   => [ self::UUID => $validScope ],
			],
			'non_array_scopes'      => [
				'metadata' => $validMetadata,
				'scopes'   => 'bad',
			],
			'invalid_metadata'      => [
				'metadata' => [
					'schema_version' => PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
					'plugin_version' => Plugin::VERSION,
					'created_at'     => 'bad',
					'updated_at'     => 100,
				],
				'scopes'   => [ self::UUID => $validScope ],
			],
			'invalid_scope_records' => [
				'metadata' => $validMetadata,
				'scopes'   => [
					self::UUID  => [ 'user_id' => 0 ],
					'not-a-uuid' => $validScope,
				],
			],
		];

		foreach ( $cases as $case => $stored ) {
			wpm_test_reset_state();
			$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] = $stored;

			$this->assertSame( [], $this->scopeRepository()->all(), $case );
			$this->assertSame( $stored, $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ], $case );
		}
	}

	public function testCapabilityCandidatesComeFromAssignedRolesOnly() :void {
		$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles(
			[
				'wpm_editor' => [
					'read'         => true,
					'edit_posts'   => true,
					'delete_posts' => false,
				],
				'wpm_admin'  => [
					'manage_options' => true,
				],
			]
		);
		$GLOBALS[ 'wpm_test_users' ][ 5 ] = (object)[
			'ID'    => 5,
			'roles' => [ 'wpm_editor' ],
			'caps'  => [ 'manage_options' => true ],
		];

		$candidates = ( new CapabilityCandidateProvider() )->forUser( 5 );

		$this->assertSame( [ 'edit_posts' => true, 'read' => true ], $candidates );
		$this->assertArrayNotHasKey( 'manage_options', $candidates );
		$this->assertArrayNotHasKey( 'delete_posts', $candidates );
	}

	public function testApplicationPasswordContextCapturesOnlyValidAuthenticatedPasswordRecords() :void {
		$context = new CurrentApplicationPasswordContext();

		$context->captureAuthenticatedPassword(
			(object)[ 'ID' => 5 ],
			[ 'uuid' => strtoupper( self::UUID ) ]
		);

		$this->assertSame( 5, $context->userId() );
		$this->assertSame( self::UUID, $context->uuid() );

		$context = new CurrentApplicationPasswordContext();
		$context->captureAuthenticatedPassword(
			(object)[ 'ID' => 5 ],
			[ 'uuid' => 'not-a-uuid' ]
		);
		$this->assertSame( 1, $context->userId() );
		$this->assertSame( null, $context->uuid() );

		$context = new CurrentApplicationPasswordContext();
		$context->captureAuthenticatedPassword(
			(object)[ 'ID' => 0 ],
			[ 'uuid' => self::UUID ]
		);
		$this->assertSame( 1, $context->userId() );
		$this->assertSame( null, $context->uuid() );
	}

	public function testCapabilityGroupsClassifyWordpressPrimitiveCaps() :void {
		$groups = ( new CapabilityGroupProvider() )->group(
			[
				'read'              => true,
				'edit_posts'        => true,
				'wpm_manage_widget' => true,
			],
			[]
		);

		$this->assertSame(
			[ 'edit_posts' => true, 'read' => true ],
			$groups[ 'wordpress' ][ 'primitive' ]
		);
		$this->assertSame(
			[ 'wpm_manage_widget' => true ],
			$groups[ 'other' ][ 'primitive' ]
		);
	}

	public function testCapabilityGroupsSortCapabilitiesAlphabetically() :void {
		$groups = ( new CapabilityGroupProvider() )->group(
			[
				'upload_files' => true,
				'read'         => true,
				'edit_posts'   => true,
			],
			[
				'read_post'   => true,
				'delete_post' => true,
				'edit_post'   => true,
			]
		);

		$this->assertSame(
			[ 'edit_posts', 'read', 'upload_files' ],
			array_keys( $groups[ 'wordpress' ][ 'primitive' ] )
		);
		$this->assertSame(
			[ 'delete_post', 'edit_post', 'read_post' ],
			array_keys( $groups[ 'wordpress' ][ 'meta' ] )
		);
	}

	public function testCapabilityGroupsClassifyRegisteredMetaCaps() :void {
		$defaultMetaCaps = ( new MetaCapabilityRegistry() )->registered();
		$groups = ( new CapabilityGroupProvider() )->group(
			[],
			$defaultMetaCaps + [ 'wpm_manage_meta' => true ]
		);

		$this->assertSame(
			$defaultMetaCaps,
			$groups[ 'wordpress' ][ 'meta' ]
		);
		$this->assertSame(
			[ 'wpm_manage_meta' => true ],
			$groups[ 'other' ][ 'meta' ]
		);
	}

	public function testNormalRequestWithoutApplicationPasswordContextIsUnchanged() :void {
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );
		$enforcer = new CapabilityScopeEnforcer(
			$repository,
			new CapabilityCandidateProvider(),
			new CurrentApplicationPasswordContext(),
			new MetaCapabilityRegistry(),
			new ExpirationDatePolicy()
		);
		$allcaps = [ 'read' => true, 'edit_posts' => true ];

		$this->assertSame(
			$allcaps,
			$enforcer->filterUserCapabilities( $allcaps, [ 'edit_posts' ], [ 'edit_posts', 5 ], (object)[ 'ID' => 5 ] )
		);
	}

	public function testUnscopedApplicationPasswordIsUnchanged() :void {
		$context = new CurrentApplicationPasswordContext();
		$context->setContext( 5, self::UUID );
		$enforcer = new CapabilityScopeEnforcer(
			$this->scopeRepository(),
			new CapabilityCandidateProvider(),
			$context,
			new MetaCapabilityRegistry(),
			new ExpirationDatePolicy()
		);
		$allcaps = [ 'read' => true, 'edit_posts' => true ];

		$this->assertSame(
			$allcaps,
			$enforcer->filterUserCapabilities( $allcaps, [ 'edit_posts' ], [ 'edit_posts', 5 ], (object)[ 'ID' => 5 ] )
		);
	}

	public function testRestAuthenticatedApplicationPasswordFallbackIsScoped() :void {
		$GLOBALS[ 'wpm_test_rest_uuid' ] = self::UUID;
		$GLOBALS[ 'wpm_test_current_user_id' ] = 5;

		$enforcer = $this->enforcerWithScope( self::UUID, 5, [ 'read' => true ] );
		$this->extractContext( $enforcer )->setContext( 0, '' );
		$filtered = $enforcer->filterUserCapabilities(
			[ 'read' => true, 'edit_posts' => true ],
			[ 'edit_posts' ],
			[ 'edit_posts', 5 ],
			(object)[ 'ID' => 5 ]
		);

		$this->assertTrue( $filtered[ 'read' ] );
		$this->assertFalse( $filtered[ 'edit_posts' ] );
	}

	public function testScopedApplicationPasswordLosesCapsOutsideAllowlist() :void {
		$enforcer = $this->enforcerWithScope( self::UUID, 5, [ 'read' => true, 'edit_posts' => true ] );
		$allcaps = [
			'read'         => true,
			'edit_posts'   => true,
			'upload_files' => true,
			'delete_posts' => true,
		];

		$filtered = $enforcer->filterUserCapabilities( $allcaps, [ 'upload_files' ], [ 'upload_files', 5 ], (object)[ 'ID' => 5 ] );

		$this->assertTrue( $filtered[ 'read' ] );
		$this->assertTrue( $filtered[ 'edit_posts' ] );
		$this->assertFalse( $filtered[ 'upload_files' ] );
		$this->assertFalse( $filtered[ 'delete_posts' ] );
	}

	public function testScopedApplicationPasswordDeniesSavedCapsThatAreNoLongerRoleDerived() :void {
		$enforcer = $this->enforcerWithScope( self::UUID, 5, [ 'read' => true, 'upload_files' => true ] );
		$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles(
			[
				'wpm_editor' => [
					'read' => true,
				],
			]
		);

		$filtered = $enforcer->filterUserCapabilities(
			[ 'read' => true, 'upload_files' => true ],
			[ 'upload_files' ],
			[ 'upload_files', 5 ],
			(object)[ 'ID' => 5 ]
		);

		$this->assertTrue( $filtered[ 'read' ] );
		$this->assertFalse( $filtered[ 'upload_files' ] );
	}

	public function testEnforcementNeverGrantsMissingCaps() :void {
		$enforcer = $this->enforcerWithScope( self::UUID, 5, [ 'read' => true, 'edit_posts' => true ] );

		$filtered = $enforcer->filterUserCapabilities(
			[ 'read' => true ],
			[ 'edit_posts' ],
			[ 'edit_posts', 5 ],
			(object)[ 'ID' => 5 ]
		);

		$this->assertTrue( $filtered[ 'read' ] );
		$this->assertArrayNotHasKey( 'edit_posts', $filtered );
	}

	public function testScopeUserMismatchFailsClosed() :void {
		$enforcer = $this->enforcerWithScope( self::UUID, 5, [ 'read' => true ] );
		$context = $this->extractContext( $enforcer );
		$context->setContext( 9, self::UUID );

		$filtered = $enforcer->filterUserCapabilities(
			[ 'read' => true, 'edit_posts' => true ],
			[ 'read' ],
			[ 'read', 5 ],
			(object)[ 'ID' => 5 ]
		);

		$this->assertFalse( $filtered[ 'read' ] );
		$this->assertFalse( $filtered[ 'edit_posts' ] );
	}

	public function testRegisteredMetaCapabilitiesCanBeDenied() :void {
		$enforcer = $this->enforcerWithScope(
			self::UUID,
			5,
			[ 'read' => true, 'edit_posts' => true ],
			[ 'edit_post' => true ]
		);

		$this->assertSame(
			[ 'edit_posts' ],
			$enforcer->filterMetaCaps( [ 'edit_posts' ], 'edit_post', 5, [ 123 ] )
		);
		$this->assertSame(
			[ 'do_not_allow' ],
			$enforcer->filterMetaCaps( [ 'delete_posts' ], 'delete_post', 5, [ 123 ] )
		);
		$this->assertSame(
			[ 'edit_posts' ],
			$enforcer->filterMetaCaps( [ 'edit_posts' ], 'custom_meta_cap', 5, [ 123 ] )
		);
	}

	public function testScopedSuperAdminMappedPrimitiveCapsOutsideAllowlistAreDenied() :void {
		$GLOBALS[ 'wpm_test_is_multisite' ] = true;
		$GLOBALS[ 'wpm_test_super_admins' ] = [ 5 ];
		$enforcer = $this->enforcerWithScope( self::UUID, 5, [ 'read' => true ] );

		$this->assertSame(
			[ 'do_not_allow' ],
			$enforcer->filterMetaCaps( [ 'manage_options' ], 'manage_network_options', 5, [] )
		);
	}

	public function testScopedSuperAdminMappedPrimitiveCapsInsideAllowlistArePreserved() :void {
		$GLOBALS[ 'wpm_test_is_multisite' ] = true;
		$GLOBALS[ 'wpm_test_super_admins' ] = [ 5 ];
		$enforcer = $this->enforcerWithScope( self::UUID, 5, [ 'manage_options' => true, 'read' => true ] );
		$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles(
			[
				'wpm_editor' => [
					'manage_options' => true,
					'read'           => true,
				],
			]
		);

		$this->assertSame(
			[ 'manage_options' ],
			$enforcer->filterMetaCaps( [ 'manage_options' ], 'manage_network_options', 5, [] )
		);
	}

	public function testMetaCapabilityRegistryUsesMandateFilter() :void {
		add_filter(
			'mandate_meta_capabilities',
			static function ( array $capabilities ) :array {
				$capabilities[] = 'wpm_manage_widget';
				return $capabilities;
			}
		);

		$this->assertArrayHasKey( 'wpm_manage_widget', ( new MetaCapabilityRegistry() )->registered() );
	}

	public function testAdminPostIgnoresNonPostRequests() :void {
		$this->seedAdminFixture();
		$_SERVER[ 'REQUEST_METHOD' ] = 'GET';
		$_POST[ 'mandate_action' ] = 'save_scope';

		$this->adminPage()->handlePost();

		$this->assertSame( [], $this->scopeRepository()->all() );
	}

	public function testAdminPageRegistersPluginActionLinksFilter() :void {
		$adminPage = $this->adminPage();

		$adminPage->registerHooks();

		$filterName = 'plugin_action_links_'.PluginIdentity::MAIN_FILE;
		$callbacks = $GLOBALS[ 'wpm_test_filters' ][ $filterName ][ 10 ] ?? [];
		$this->assertCount( 1, $callbacks );
		$this->assertSame( [ $adminPage, 'addSettingsActionLink' ], $callbacks[ 0 ][ 'callback' ] );
		$this->assertSame( 1, $callbacks[ 0 ][ 'accepted_args' ] );
	}

	public function testAdminPagePrependsSettingsPluginActionLink() :void {
		$this->adminPage()->registerHooks();

		$links = apply_filters(
			'plugin_action_links_'.PluginIdentity::MAIN_FILE,
			[ 'deactivate' => 'existing-link' ]
		);

		$this->assertSame( [ 'settings', 'deactivate' ], array_keys( $links ) );
		$this->assertSame( 'existing-link', $links[ 'deactivate' ] );

		$href = $this->actionLinkHref( $links[ 'settings' ] );
		$this->assertSame( 'https', parse_url( $href, PHP_URL_SCHEME ) );
		$this->assertSame( 'example.test', parse_url( $href, PHP_URL_HOST ) );
		$this->assertSame( '/wp-admin/tools.php', parse_url( $href, PHP_URL_PATH ) );

		$query = parse_url( $href, PHP_URL_QUERY );
		$this->assertIsString( $query );
		parse_str( $query, $params );
		$this->assertSame( [ 'page' => Plugin::MENU_SLUG ], $params );
	}

	public function testAdminAssetsEnqueueOnlyForRegisteredPageHookAndExistingDistFiles() :void {
		$root = sys_get_temp_dir().'/mandate-admin-assets-'.bin2hex( random_bytes( 4 ) );
		$dist = $root.'/assets/dist';
		$pluginFile = $root.'/'.PluginIdentity::MAIN_FILE;
		if ( !mkdir( $dist, 0777, true ) && !is_dir( $dist ) ) {
			throw new RuntimeException( 'Failed to create admin asset fixture directory.' );
		}
		file_put_contents( $pluginFile, "<?php\n" );
		file_put_contents( $dist.'/admin-page.css', "body{}\n" );

		try {
			$scopeRepository = $this->scopeRepository();
			$passwordRepository = new ApplicationPasswordRepository();
			$candidateProvider = new CapabilityCandidateProvider();
			$descriptionProvider = new CapabilityDescriptionProvider();
			$metaRegistry = new MetaCapabilityRegistry();
			$groupProvider = new CapabilityGroupProvider();
			$expirationDatePolicy = new ExpirationDatePolicy();
			$roleProvider = new AdminUserRoleProvider();
			$trustedHtmlSanitizer = new AdminTrustedHtmlSanitizer();
			$formSecurity = new AdminScopeFormSecurity( $trustedHtmlSanitizer );
			$adminPage = new AdminPage(
				$scopeRepository,
				$passwordRepository,
				$candidateProvider,
				$metaRegistry,
				$pluginFile,
				$expirationDatePolicy,
				$roleProvider,
				$formSecurity,
				new AdminPageViewDataBuilder(
					$scopeRepository,
					$passwordRepository,
					$candidateProvider,
					$descriptionProvider,
					$metaRegistry,
					$groupProvider,
					$expirationDatePolicy,
					$roleProvider,
					$formSecurity,
					$trustedHtmlSanitizer
				),
				new AdminTemplateRenderer()
			);

			$adminPage->registerMenu();
			$adminPage->enqueueAssets( 'dashboard_page_not_mandate' );
			$this->assertSame( [], $GLOBALS[ 'wpm_test_enqueued_styles' ] );
			$this->assertSame( [], $GLOBALS[ 'wpm_test_enqueued_scripts' ] );

			$adminPage->enqueueAssets( 'tools_page_'.PluginIdentity::SLUG );
			$this->assertArrayHasKey( 'mandate-admin-page', $GLOBALS[ 'wpm_test_enqueued_styles' ] );
			$this->assertSame( [], $GLOBALS[ 'wpm_test_enqueued_scripts' ] );
			$this->assertSame(
				'https://example.test/wp-content/plugins/'.basename( $root ).'/assets/dist/admin-page.css',
				$GLOBALS[ 'wpm_test_enqueued_styles' ][ 'mandate-admin-page' ][ 'src' ]
			);
		}
		finally {
			$this->removeDirectory( $root );
		}
	}

	public function testAdminPostIgnoresUnrelatedPostRequests() :void {
		$this->seedAdminFixture();
		$_SERVER[ 'REQUEST_METHOD' ] = 'POST';

		$this->adminPage()->handlePost();

		$this->assertSame( [], $this->scopeRepository()->all() );
	}

	public function testAdminPostRequiresManageOptionsBeforeMutation() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_current_user_caps' ] = [];
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true );

		$this->assertThrowsRuntimeException(
			fn() => $this->adminPage()->handlePost()
		);
		$this->assertSame( [], $this->scopeRepository()->all() );
	}

	public function testAdminPostRequiresActionScopedNonceBeforeMutation() :void {
		$this->seedAdminFixture();
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], false );

		$this->assertThrowsRuntimeException(
			fn() => $this->adminPage()->handlePost()
		);
		$this->assertSame( [], $this->scopeRepository()->all() );
	}

	public function testAdminPostRejectsUnownedPassword() :void {
		$this->seedAdminFixture();
		$this->submitScopePost( 'save_scope', 5, self::OTHER_UUID, [ 'read' ], [], true );

		$location = $this->handlePostExpectRedirect( $this->adminPage() );

		$this->assertSame( [], $this->scopeRepository()->all() );
		$this->assertSame( 'invalid', $this->redirectMessage( $location ) );
	}

	public function testAdminPostSavesOwnedScopeWithCandidateCapsOnly() :void {
		$this->seedAdminFixture();
		$this->submitScopePost(
			'save_scope',
			5,
			self::UUID,
			[ 'read', 'upload_files', 'manage_options', 'Bad Cap' ],
			[ 'edit_post', 'wpm_missing_meta' ],
			true
		);

		$repository = $this->scopeRepository();
		$this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertNotNull( $record );
		$this->assertTrue( $record[ 'capabilities_restricted' ] );
		$this->assertSame(
			[ 'read' => true, 'upload_files' => true ],
			$record[ 'allowed_caps' ]
		);
		$this->assertSame( [ 'edit_post' => true ], $record[ 'allowed_meta_caps' ] );
		$this->assertNull( $record[ 'expires_on' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
		$this->assertSame( false, $GLOBALS[ 'wpm_test_autoload' ][ PluginOptionsRepository::OPTION_NAME ] );
	}

	public function testAdminPostAllSelectedScopeDeletesStoredScope() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1 );
		$repository->save( self::OTHER_UUID, 9, [ 'read' => true ], [], [], 1 );
		$this->submitScopePost(
			'save_scope',
			5,
			self::UUID,
			[ 'read', 'edit_posts', 'upload_files' ],
			array_keys( ( new MetaCapabilityRegistry() )->registered() ),
			true
		);

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$storedScopes = $this->storedScopes();
		$this->assertArrayNotHasKey( self::UUID, $storedScopes );
		$this->assertArrayHasKey( self::OTHER_UUID, $storedScopes );
		$this->assertSame( 'reset', $this->redirectMessage( $location ) );
	}

	public function testAdminPostRefusesMultisiteSuperAdminScopeWithoutMutation() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_is_multisite' ] = true;
		$GLOBALS[ 'wpm_test_super_admins' ] = [ 5 ];
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true );

		$repository = $this->scopeRepository();
		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( [], $repository->all() );
		$this->assertSame( 'super_admin_unsupported', $this->redirectMessage( $location ) );
	}

	public function testAdminRenderFlagsChangedRoleSnapshot() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );

		$html = $this->renderAdminPage( $repository );

		$this->assertTrue( str_contains( $html, 'data-wpm-role-snapshot-status="changed"' ) );
	}

	public function testAdminRenderDoesNotFlagMatchingRoleSnapshot() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1 );

		$html = $this->renderAdminPage( $repository );

		$this->assertFalse( str_contains( $html, 'data-wpm-role-snapshot-status="changed"' ) );
	}

	public function testAdminRenderDoesNotFlagLegacyUnknownRoleSnapshot() :void {
		$this->seedAdminFixture();
		$optionsRepository = new PluginOptionsRepository();
		$repository = $this->scopeRepository( $optionsRepository );
		$optionsRepository->replaceScopes(
			[
				self::UUID => [
					'user_id'           => 5,
					'allowed_caps'      => [ 'read' => true ],
					'allowed_meta_caps' => [],
					'updated_at'        => 100,
					'updated_by'        => 1,
				],
			]
		);

		$html = $this->renderAdminPage( $repository );

		$this->assertFalse( str_contains( $html, 'data-wpm-role-snapshot-status="changed"' ) );
	}

	public function testAdminRenderAddsTooltipAttributesForDescribedCapabilitiesOnly() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_roles' ]->roles[ 'wpm_editor' ][ 'wpm_manage_widget' ] = true;

		$html = $this->renderAdminPage( $this->scopeRepository() );

		$described = $this->capabilityCodeAttributes( $html, 'upload_files' );
		$this->assertSame( '', $described[ 'data-wpm-tooltip' ] );
		$this->assertArrayHasKey( 'data-wpm-tooltip-text', $described );
		$this->assertNotSame( '', $described[ 'data-wpm-tooltip-text' ] );
		$this->assertSame( '0', $described[ 'tabindex' ] );

		$custom = $this->capabilityCodeAttributes( $html, 'wpm_manage_widget' );
		$this->assertArrayNotHasKey( 'data-wpm-tooltip', $custom );
		$this->assertArrayNotHasKey( 'data-wpm-tooltip-text', $custom );
		$this->assertArrayNotHasKey( 'tabindex', $custom );
	}

	public function testAdminPageViewDataBuilderEmitsStructuredContract() :void {
		$this->seedAdminFixture();
		$_GET = [
			'page'              => Plugin::MENU_SLUG,
			'user_id'           => '5',
			'app_password_uuid' => self::UUID,
		];

		$data = $this->adminPageViewDataBuilder( $this->scopeRepository() )->build();

		foreach ( [ 'hrefs', 'strings', 'flags', 'classes', 'vars', 'trustedHtml' ] as $topLevelKey ) {
			$this->assertArrayHasKey( $topLevelKey, $data );
		}
		$this->assertArrayNotHasKey( 'content', $data );
		$this->assertSame( 'wrap mandate', $data[ 'classes' ][ 'root' ] );
		$this->assertTrue( $data[ 'flags' ][ 'has_passwords' ] );
		$this->assertTrue( $data[ 'flags' ][ 'show_scope_form' ] );
		$this->assertIsString( $data[ 'hrefs' ][ 'selection_form_action' ] );
		$this->assertIsString( $data[ 'hrefs' ][ 'scope_form_action' ] );
		$this->assertIsString( $data[ 'trustedHtml' ][ 'user_dropdown' ] );
		$this->assertIsString( $data[ 'trustedHtml' ][ 'scope_nonce_fields' ] );

		$selectionForm = $data[ 'vars' ][ 'selection_form' ];
		$this->assertSame( 5, $selectionForm[ 'selected_user_id' ] );
		$this->assertSame( self::UUID, $selectionForm[ 'selected_uuid' ] );
		$this->assertSame( Plugin::MENU_SLUG, $selectionForm[ 'page_slug' ] );
		$this->assertTrue( $selectionForm[ 'role_summary' ][ 'has_roles' ] );
		$this->assertSame( 'wpm_editor', $selectionForm[ 'role_summary' ][ 'rows' ][ 0 ][ 'slug' ] );
		$this->assertSame( self::UUID, $selectionForm[ 'password_options' ][ 0 ][ 'uuid' ] );
		$this->assertTrue( $selectionForm[ 'password_options' ][ 0 ][ 'selected' ] );
		$this->assertFalse( $selectionForm[ 'password_summary' ][ 'sections' ][ 0 ][ 'show_divider_before' ] );
		$this->assertTrue( $selectionForm[ 'password_summary' ][ 'sections' ][ 1 ][ 'show_divider_before' ] );

		$scopeForm = $data[ 'vars' ][ 'scope_form' ];
		$this->assertSame( 'mandate-scope-form', $scopeForm[ 'id' ] );
		$this->assertSame( self::UUID, $scopeForm[ 'uuid' ] );
		$this->assertSame( 'wordpress', $scopeForm[ 'tabs' ][ 0 ][ 'key' ] );
		$this->assertSame( 'other', $scopeForm[ 'tabs' ][ 1 ][ 'key' ] );
		$this->assertFalse( $scopeForm[ 'actions' ][ 0 ][ 'disabled' ] );

		$uploadFiles = $this->capabilityItemFromViewData( $data, 'upload_files' );
		$this->assertSame( 'allowed_caps', $uploadFiles[ 'field_name' ] );
		$this->assertTrue( $uploadFiles[ 'checked' ] );
		$this->assertTrue( $uploadFiles[ 'has_tooltip' ] );
		$this->assertIsString( $uploadFiles[ 'tooltip_text' ] );
	}

	public function testAdminPageViewDataBuilderEmitsSuperAdminUnsupportedState() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_is_multisite' ] = true;
		$GLOBALS[ 'wpm_test_super_admins' ] = [ 5 ];
		$_GET = [
			'page'              => Plugin::MENU_SLUG,
			'user_id'           => '5',
			'app_password_uuid' => self::UUID,
		];

		$data = $this->adminPageViewDataBuilder( $this->scopeRepository() )->build();
		$scopeForm = $data[ 'vars' ][ 'scope_form' ];

		$this->assertTrue( $scopeForm[ 'super_admin_notice' ][ 'is_visible' ] );
		$this->assertTrue( $scopeForm[ 'actions' ][ 0 ][ 'disabled' ] );
		$this->assertFalse( $scopeForm[ 'actions' ][ 1 ][ 'disabled' ] );
	}

	public function testAdminPageViewDataBuilderEmitsRoleSnapshotAndExpirationState() :void {
		$this->seedAdminFixture();
		$_GET = [
			'page'              => Plugin::MENU_SLUG,
			'user_id'           => '5',
			'app_password_uuid' => self::UUID,
		];
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [], [], [], 1, '2026-05-24', false );

		$data = $this->adminPageViewDataBuilder( $repository )->build();
		$summary = $data[ 'vars' ][ 'selection_form' ][ 'password_summary' ];
		$scopeDetails = $summary[ 'sections' ][ 1 ][ 'details' ];

		$this->assertCount( 1, $summary[ 'warnings' ] );
		$this->assertSame( 'changed', $summary[ 'warnings' ][ 0 ][ 'role_snapshot_status' ] );
		$this->assertSame( 'date', $scopeDetails[ 1 ][ 'state' ] );
		$this->assertSame( '2026-05-24', $scopeDetails[ 1 ][ 'input' ][ 'value' ] );
	}

	public function testAdminTemplateRendererRendersConfinedTemplate() :void {
		$html = ( new AdminTemplateRenderer() )->render(
			'partials/notice.php',
			[
				'notice' => [
					'is_visible' => true,
					'classes'    => 'notice notice-success',
					'text'       => 'Rendered fixture',
				],
			]
		);

		$this->assertStringContainsString( 'Rendered fixture', $html );
	}

	public function testAdminTemplateRendererRejectsInvalidTemplatePaths() :void {
		$renderer = new AdminTemplateRenderer();

		foreach ( [
			'',
			' partials/notice.php',
			'partials/notice.php ',
			'../AdminPage.php',
			'/partials/notice.php',
			'C:/Windows/win.ini',
			'php://filter/resource=partials/notice.php',
			'partials\\notice.php',
			'partials/..%2Fnotice.php',
			'partials/notice.txt',
			'partials/missing.php',
			'partials/.hidden.php',
			"partials/notice.php\0",
		] as $template ) {
			$this->assertThrowsRuntimeException( static fn() => $renderer->render( $template, [] ), $template );
		}
	}

	public function testAdminTemplateRendererRejectsUnsafeDataKeys() :void {
		$renderer = new AdminTemplateRenderer();
		$notice = [
			'is_visible' => true,
			'classes'    => 'notice notice-success',
			'text'       => 'Rendered fixture',
		];

		foreach ( [ 'this', 'GLOBALS', '_POST', 'bad-key', '1bad' ] as $key ) {
			$this->assertThrowsRuntimeException(
				static fn() => $renderer->render( 'partials/notice.php', [ 'notice' => $notice, $key => 'bad' ] ),
				$key
			);
		}

		$this->assertThrowsRuntimeException(
			static fn() => $renderer->render( 'partials/notice.php', [ 'notice' => $notice, 0 => 'bad' ] )
		);
	}

	public function testAdminTrustedHtmlSanitizerStripsDropdownMarkupOutsideAllowlist() :void {
		$html = ( new AdminTrustedHtmlSanitizer() )->dropdown(
			'<select name="user_id" id="mandate-user" class="wide" onchange="evil()">'
			.'<option value="5" selected="selected" onclick="evil()">User<script>alert(1)</script></option>'
			.'</select><input type="text" name="bad" value="bad" />'
		);
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );

		$select = $this->firstElement( $xpath, '//select' );
		$this->assertSame( 'user_id', $select->getAttribute( 'name' ) );
		$this->assertSame( 'mandate-user', $select->getAttribute( 'id' ) );
		$this->assertSame( 'wide', $select->getAttribute( 'class' ) );
		$this->assertSame( '', $select->getAttribute( 'onchange' ) );

		$option = $this->firstElement( $xpath, '//option' );
		$this->assertSame( '5', $option->getAttribute( 'value' ) );
		$this->assertSame( 'selected', $option->getAttribute( 'selected' ) );
		$this->assertSame( '', $option->getAttribute( 'onclick' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//script|//input' ) );
	}

	public function testAdminTrustedHtmlSanitizerKeepsOnlyHiddenNonceInputs() :void {
		$html = ( new AdminTrustedHtmlSanitizer() )->nonceFields(
			'<input type="hidden" id="mandate-save-nonce" name="mandate_save_nonce" value="abc" onclick="evil()" />'
			.'<input type="text" name="bad" value="bad" />'
			.'<select name="bad"><option value="bad">Bad</option></select>'
		);
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );

		$this->assertSame( 1, $this->nodeCount( $xpath, '//input' ) );
		$input = $this->firstElement( $xpath, '//input' );
		$this->assertSame( 'hidden', $input->getAttribute( 'type' ) );
		$this->assertSame( 'mandate-save-nonce', $input->getAttribute( 'id' ) );
		$this->assertSame( 'mandate_save_nonce', $input->getAttribute( 'name' ) );
		$this->assertSame( 'abc', $input->getAttribute( 'value' ) );
		$this->assertSame( '', $input->getAttribute( 'onclick' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//select|//option' ) );
		$this->assertFalse( str_contains( $html, 'Bad' ) );
	}

	public function testAdminScopeFormSecurityEmitsOnlyActionNonceFields() :void {
		$html = ( new AdminScopeFormSecurity( new AdminTrustedHtmlSanitizer() ) )->nonceFields( 5, self::UUID );
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );

		$this->assertSame( 2, $this->nodeCount( $xpath, '//input' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//input[@type="hidden" and @id="mandate_save_scope_nonce" and @name="mandate_save_scope_nonce"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//input[@type="hidden" and @id="mandate_clear_scope_nonce" and @name="mandate_clear_scope_nonce"]' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//input[@name="_wp_http_referer"]' ) );
	}

	public function testAdminRenderEscapesMaliciousPasswordAndRoleDisplayData() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles(
			[
				'wpm_editor' => [
					'read'         => true,
					'edit_posts'   => true,
					'upload_files' => true,
				],
			],
			[
				'wpm_editor' => '<img src=x onerror=alert(1)>',
			]
		);
		WP_Application_Passwords::$passwordsByUser[ 5 ][ 0 ][ 'name' ] = '<script>alert(1)</script>';
		WP_Application_Passwords::$passwordsByUser[ 5 ][ 0 ][ 'app_id' ] = 'app" autofocus onfocus="alert(1)';

		$xpath = new DOMXPath( $this->documentFromHtml( $this->renderAdminPage( $this->scopeRepository() ) ) );

		$this->assertSame( 0, $this->nodeCount( $xpath, '//script|//img' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//@*[starts-with(name(), "on")]' ) );
	}

	public function testAdminPostClearsOnlyOwnedScope() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );
		$repository->save( self::OTHER_UUID, 9, [ 'read' => true ], [], [], 1 );
		$this->submitScopePost( 'clear_scope', 5, self::UUID, [], [], true );

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( null, $repository->findForUser( 5, self::UUID ) );
		$storedScopes = $this->storedScopes();
		$this->assertArrayNotHasKey( self::UUID, $storedScopes );
		$this->assertArrayHasKey( self::OTHER_UUID, $storedScopes );
		$this->assertSame( 'reset', $this->redirectMessage( $location ) );
	}

	public function testAdminPostClearRefusesScopeOwnedByDifferentUser() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 9, [ 'read' => true ], [], [], 1 );
		$this->submitScopePost( 'clear_scope', 5, self::UUID, [], [], true );

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 9, $repository->find( self::UUID )[ 'user_id' ] );
		$this->assertArrayHasKey( self::UUID, $this->storedScopes() );
		$this->assertSame( 'invalid', $this->redirectMessage( $location ) );
	}

	public function testScopeRepositoryFindAndDeleteRequireMatchingUser() :void {
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );

		$this->assertSame( null, $repository->findForUser( 9, self::UUID ) );
		$this->assertFalse( $repository->deleteForUser( 9, self::UUID ) );
		$this->assertArrayHasKey( self::UUID, $repository->all() );
		$this->assertTrue( $repository->deleteForUser( 5, self::UUID ) );
		$this->assertSame( [], $repository->all() );
	}

	public function testDeletedApplicationPasswordPrunesScopeRecord() :void {
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );
		$this->assertArrayHasKey( self::UUID, $repository->all() );

		$repository->deleteForApplicationPassword( 5, [ 'uuid' => self::UUID ] );

		$this->assertSame( [], $repository->all() );
	}

	public function testDeletedApplicationPasswordDoesNotPruneScopeForDifferentUser() :void {
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 9, [ 'read' => true ], [], [], 1 );

		$repository->deleteForApplicationPassword( 5, [ 'uuid' => self::UUID ] );

		$this->assertSame( 9, $repository->find( self::UUID )[ 'user_id' ] );
	}

	public function testPluginDeleteHookPrunesStoredScopeRecord() :void {
		$this->scopeRepository()->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );
		$this->assertArrayHasKey( self::UUID, $this->storedScopes() );

		Plugin::boot( $this->pluginFile() );
		do_action( 'wp_delete_application_password', 5, [ 'uuid' => self::UUID ] );

		$this->assertArrayNotHasKey( self::UUID, $this->storedScopes() );
	}

	public function testPluginDeleteHookDoesNotPruneScopeForDifferentUser() :void {
		$this->scopeRepository()->save( self::UUID, 9, [ 'read' => true ], [], [], 1 );

		Plugin::boot( $this->pluginFile() );
		do_action( 'wp_delete_application_password', 5, [ 'uuid' => self::UUID ] );

		$storedScopes = $this->storedScopes();
		$this->assertArrayHasKey( self::UUID, $storedScopes );
		$this->assertSame( 9, $storedScopes[ self::UUID ][ 'user_id' ] );
	}

	private function adminPage( ?ScopeRepository $repository = null ) :AdminPage {
		$repository = $repository ?? $this->scopeRepository();
		$passwordRepository = new ApplicationPasswordRepository();
		$candidateProvider = new CapabilityCandidateProvider();
		$descriptionProvider = new CapabilityDescriptionProvider();
		$metaRegistry = new MetaCapabilityRegistry();
		$groupProvider = new CapabilityGroupProvider();
		$expirationDatePolicy = new ExpirationDatePolicy();
		$roleProvider = new AdminUserRoleProvider();
		$trustedHtmlSanitizer = new AdminTrustedHtmlSanitizer();
		$formSecurity = new AdminScopeFormSecurity( $trustedHtmlSanitizer );
		$viewDataBuilder = new AdminPageViewDataBuilder(
			$repository,
			$passwordRepository,
			$candidateProvider,
			$descriptionProvider,
			$metaRegistry,
			$groupProvider,
			$expirationDatePolicy,
			$roleProvider,
			$formSecurity,
			$trustedHtmlSanitizer
		);

		return new AdminPage(
			$repository,
			$passwordRepository,
			$candidateProvider,
			$metaRegistry,
			$this->pluginFile(),
			$expirationDatePolicy,
			$roleProvider,
			$formSecurity,
			$viewDataBuilder,
			new AdminTemplateRenderer()
		);
	}

	private function adminPageViewDataBuilder( ScopeRepository $repository ) :AdminPageViewDataBuilder {
		$roleProvider = new AdminUserRoleProvider();
		$trustedHtmlSanitizer = new AdminTrustedHtmlSanitizer();
		$formSecurity = new AdminScopeFormSecurity( $trustedHtmlSanitizer );

		return new AdminPageViewDataBuilder(
			$repository,
			new ApplicationPasswordRepository(),
			new CapabilityCandidateProvider(),
			new CapabilityDescriptionProvider(),
			new MetaCapabilityRegistry(),
			new CapabilityGroupProvider(),
			new ExpirationDatePolicy(),
			$roleProvider,
			$formSecurity,
			$trustedHtmlSanitizer
		);
	}

	private function pluginFile() :string {
		return dirname( __DIR__, 2 ).'/'.PluginIdentity::MAIN_FILE;
	}

	private function seedAdminFixture() :void {
		$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles(
			[
				'wpm_editor' => [
					'read'         => true,
					'edit_posts'   => true,
					'upload_files' => true,
				],
			]
		);
		$GLOBALS[ 'wpm_test_users' ][ 5 ] = (object)[
			'ID'    => 5,
			'roles' => [ 'wpm_editor' ],
		];
		WP_Application_Passwords::$passwordsByUser = [
			5 => [
				[
					'uuid'      => self::UUID,
					'name'      => 'Client',
					'app_id'    => '',
					'created'   => 0,
					'last_used' => 0,
				],
			],
		];
	}

	/**
	 * @param string[] $allowedCaps
	 * @param string[] $allowedMetaCaps
	 */
	private function submitScopePost(
		string $action,
		int $userId,
		string $uuid,
		array $allowedCaps,
		array $allowedMetaCaps,
		bool $withNonce
	) :void {
		$_SERVER[ 'REQUEST_METHOD' ] = 'POST';
		$_POST = [
			'mandate_action'    => $action,
			'user_id'           => (string)$userId,
			'app_password_uuid' => $uuid,
			'allowed_caps'      => $allowedCaps,
			'allowed_meta_caps' => $allowedMetaCaps,
		];
		if ( $withNonce ) {
			$formSecurity = new AdminScopeFormSecurity( new AdminTrustedHtmlSanitizer() );
			$nonceName = $formSecurity->nonceName( $action );
			$_POST[ $nonceName ] = wpm_test_set_valid_nonce(
				$nonceName,
				$formSecurity->nonceAction( $action, $userId, $uuid )
			);
		}
	}

	private function handlePostExpectRedirect( AdminPage $adminPage ) :string {
		try {
			$adminPage->handlePost();
		}
		catch ( Wpm_Test_Redirect_Exception $redirect ) {
			return $redirect->location;
		}

		throw new RuntimeException( 'Expected admin POST to redirect.' );
	}

	private function redirectMessage( string $location ) :string {
		$query = parse_url( $location, PHP_URL_QUERY );
		if ( !is_string( $query ) ) {
			return '';
		}

		parse_str( $query, $params );
		$message = $params[ 'mandate_message' ] ?? '';
		return is_scalar( $message ) ? (string)$message : '';
	}

	private function actionLinkHref( string $html ) :string {
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );
		return $this->firstElement( $xpath, '//a' )->getAttribute( 'href' );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function storedScopes() :array {
		$scopes = $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ][ 'scopes' ] ?? [];
		return is_array( $scopes ) ? $scopes : [];
	}

	private function removeDirectory( string $directory ) :void {
		if ( !is_dir( $directory ) ) {
			return;
		}

		$items = scandir( $directory );
		if ( $items === false ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}

			$path = $directory.DIRECTORY_SEPARATOR.$item;
			if ( is_dir( $path ) && !is_link( $path ) ) {
				$this->removeDirectory( $path );
			}
			elseif ( is_file( $path ) || is_link( $path ) ) {
				unlink( $path );
			}
		}

		rmdir( $directory );
	}

	private function renderAdminPage( ScopeRepository $repository ) :string {
		$_GET = [
			'page'              => Plugin::MENU_SLUG,
			'user_id'           => '5',
			'app_password_uuid' => self::UUID,
		];

		ob_start();
		try {
			$this->adminPage( $repository )->render();
			return (string)ob_get_clean();
		}
		catch ( Throwable $throwable ) {
			ob_end_clean();
			throw $throwable;
		}
	}

	private function documentFromHtml( string $html ) :DOMDocument {
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		try {
			$document->loadHTML( '<!doctype html><html><body>'.$html.'</body></html>' );
			libxml_clear_errors();
		}
		finally {
			libxml_use_internal_errors( $previous );
		}

		return $document;
	}

	private function firstElement( DOMXPath $xpath, string $query ) :DOMElement {
		$nodes = $xpath->query( $query );
		if ( !$nodes instanceof DOMNodeList || $nodes->length < 1 ) {
			throw new RuntimeException( 'Expected DOM element for query '.$query.'.' );
		}

		$node = $nodes->item( 0 );
		if ( !$node instanceof DOMElement ) {
			throw new RuntimeException( 'Expected DOM node to be an element.' );
		}

		return $node;
	}

	private function nodeCount( DOMXPath $xpath, string $query ) :int {
		$nodes = $xpath->query( $query );
		if ( !$nodes instanceof DOMNodeList ) {
			throw new RuntimeException( 'Expected DOM query to return a node list.' );
		}

		return $nodes->length;
	}

	/**
	 * @return array<string,string>
	 */
	private function capabilityCodeAttributes( string $html, string $capability ) :array {
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );
		$capabilityLiteral = json_encode( $capability, JSON_THROW_ON_ERROR );
		$nodes = $xpath->query( '//code[normalize-space(.) = '.$capabilityLiteral.']' );
		if ( !$nodes instanceof DOMNodeList || $nodes->length < 1 ) {
			throw new RuntimeException( 'Expected rendered capability code for '.$capability.'.' );
		}

		$node = $nodes->item( 0 );
		if ( !$node instanceof DOMElement ) {
			throw new RuntimeException( 'Expected capability node to be an element.' );
		}

		$attributes = [];
		foreach ( $node->attributes ?? [] as $attribute ) {
			$attributes[ $attribute->name ] = $attribute->value;
		}

		return $attributes;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function capabilityItemFromViewData( array $data, string $capability ) :array {
		foreach ( $data[ 'vars' ][ 'scope_form' ][ 'panels' ] as $panel ) {
			foreach ( $panel[ 'sections' ] as $section ) {
				foreach ( $section[ 'items' ] as $item ) {
					if ( $item[ 'name' ] === $capability ) {
						return $item;
					}
				}
			}
		}

		throw new RuntimeException( 'Expected capability item for '.$capability.'.' );
	}

	/**
	 * @param array<string,true> $allowedCaps
	 * @param array<string,true> $allowedMetaCaps
	 */
	private function enforcerWithScope(
		string $uuid,
		int $userId,
		array $allowedCaps,
		array $allowedMetaCaps = []
	) :CapabilityScopeEnforcer {
		$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles(
			[
				'wpm_editor' => [
					'read'         => true,
					'edit_posts'   => true,
					'upload_files' => true,
					'delete_posts' => true,
				],
			]
		);
		$GLOBALS[ 'wpm_test_users' ][ $userId ] = (object)[
			'ID'    => $userId,
			'roles' => [ 'wpm_editor' ],
		];

		$repository = $this->scopeRepository();
		$repository->save( $uuid, $userId, $allowedCaps, $allowedMetaCaps, [ 'wpm_editor' ], 1 );

		$context = new CurrentApplicationPasswordContext();
		$context->setContext( $userId, $uuid );

		return new CapabilityScopeEnforcer(
			$repository,
			new CapabilityCandidateProvider(),
			$context,
			new MetaCapabilityRegistry(),
			new ExpirationDatePolicy()
		);
	}

	private function extractContext( CapabilityScopeEnforcer $enforcer ) :CurrentApplicationPasswordContext {
		$reflection = new ReflectionClass( $enforcer );
		$property = $reflection->getProperty( 'context' );
		$property->setAccessible( true );
		return $property->getValue( $enforcer );
	}

	private function scopeRepository( ?PluginOptionsRepository $optionsRepository = null ) :ScopeRepository {
		return new ScopeRepository( $optionsRepository ?? new PluginOptionsRepository(), new ExpirationDatePolicy() );
	}
}
