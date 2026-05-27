<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminScopeAccessPolicy;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPageViewDataBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminScopeFormSecurity;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminTemplateRenderer;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminTrustedHtmlSanitizer;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminUserRoleProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\ApplicationPasswordScopeColumn;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityDescriptionProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ApplicationPasswordExpirationReaper;
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

	public function testScopeNormalizationDefaultsLegacyRecordsToUnlocked() :void {
		foreach ( [ 1, 2 ] as $schemaVersion ) {
			wpm_test_reset_state();
			$stored = [
				'metadata' => [
					'schema_version' => $schemaVersion,
					'plugin_version' => '0.3.1',
					'created_at'     => 100,
					'updated_at'     => 200,
				],
				'scopes'   => [
					self::UUID => [
						'user_id'                 => 5,
						'capabilities_restricted' => true,
						'allowed_caps'            => [ 'read' => true ],
						'allowed_meta_caps'       => [],
						'expires_on'              => null,
						'roles_at_update'         => [ 'wpm_editor' ],
						'updated_at'              => 200,
						'updated_by'              => 1,
					],
				],
			];
			$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] = $stored;

			$record = $this->scopeRepository()->find( self::UUID );

			$this->assertNotNull( $record );
			$this->assertFalse( $record[ 'admin_locked' ] );
			$this->assertSame( $stored, $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] );
		}
	}

	public function testScopeRepositoryPersistsAdminLockedAndLockOnlyRecords() :void {
		$repository = $this->scopeRepository();

		$this->assertTrue( $repository->save( self::UUID, 5, [], [], [ 'wpm_editor' ], 1, null, false, true ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertNotNull( $record );
		$this->assertFalse( $record[ 'capabilities_restricted' ] );
		$this->assertSame( [], $record[ 'allowed_caps' ] );
		$this->assertTrue( $record[ 'admin_locked' ] );
		$stored = $this->storedScopes();
		$this->assertTrue( $stored[ self::UUID ][ 'admin_locked' ] );
		$this->assertSame( PluginOptionsRepository::CURRENT_SCHEMA_VERSION, $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ][ 'metadata' ][ 'schema_version' ] );
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

	public function testPluginOptionsRepositoryUsesWordPressOrgPrefixedOptionName() :void {
		$this->assertSame( 'mandate_app_security_options', PluginOptionsRepository::OPTION_NAME );
	}

	public function testPluginOptionsRepositoryIgnoresOldAptowebOptionWithoutMigration() :void {
		$oldOptionName = 'aptoweb_mandate_application_password_scoper_options';
		$oldDocument = [
			'metadata' => [
				'schema_version' => PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
				'plugin_version' => Plugin::VERSION,
				'created_at'     => 100,
				'updated_at'     => 200,
			],
			'scopes'   => [
				self::UUID => [
					'user_id'           => 5,
					'allowed_caps'      => [ 'read' => true ],
					'allowed_meta_caps' => [],
					'updated_at'        => 200,
					'updated_by'        => 1,
				],
			],
		];
		$GLOBALS[ 'wpm_test_options' ][ $oldOptionName ] = $oldDocument;

		$this->assertSame( [], $this->scopeRepository()->all() );
		$this->assertSame( $oldDocument, $GLOBALS[ 'wpm_test_options' ][ $oldOptionName ] );
		$this->assertArrayNotHasKey( PluginOptionsRepository::OPTION_NAME, $GLOBALS[ 'wpm_test_options' ] );

		$this->scopeRepository()->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );

		$this->assertSame( $oldDocument, $GLOBALS[ 'wpm_test_options' ][ $oldOptionName ] );
		$this->assertArrayHasKey( PluginOptionsRepository::OPTION_NAME, $GLOBALS[ 'wpm_test_options' ] );
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

	public function testCapabilityGroupDefinitionsCoverKnownWordpressCapabilities() :void {
		$provider = new CapabilityGroupProvider();
		$definitions = $provider->definitions();
		$this->assertSame( [ 'read', 'write', 'delete' ], $provider->actionKeys() );
		$knownWordpressCapabilities = [
			'activate_plugins',
			'add_users',
			'assign_term',
			'create_sites',
			'create_users',
			'customize',
			'delete_others_pages',
			'delete_others_posts',
			'delete_page',
			'delete_pages',
			'delete_plugins',
			'delete_post',
			'delete_posts',
			'delete_private_pages',
			'delete_private_posts',
			'delete_published_pages',
			'delete_published_posts',
			'delete_site',
			'delete_sites',
			'delete_term',
			'delete_themes',
			'delete_user',
			'delete_users',
			'edit_comment',
			'edit_dashboard',
			'edit_files',
			'edit_others_pages',
			'edit_others_posts',
			'edit_page',
			'edit_pages',
			'edit_plugins',
			'edit_post',
			'edit_posts',
			'edit_private_pages',
			'edit_private_posts',
			'edit_published_pages',
			'edit_published_posts',
			'edit_term',
			'edit_theme_options',
			'edit_themes',
			'edit_user',
			'edit_users',
			'erase_others_personal_data',
			'export',
			'export_others_personal_data',
			'import',
			'install_plugins',
			'install_themes',
			'level_0',
			'level_1',
			'level_2',
			'level_3',
			'level_4',
			'level_5',
			'level_6',
			'level_7',
			'level_8',
			'level_9',
			'level_10',
			'list_users',
			'manage_categories',
			'manage_links',
			'manage_network',
			'manage_network_options',
			'manage_network_plugins',
			'manage_network_themes',
			'manage_network_users',
			'manage_options',
			'manage_privacy_options',
			'manage_sites',
			'moderate_comments',
			'promote_users',
			'publish_pages',
			'publish_posts',
			'read',
			'read_page',
			'read_post',
			'read_private_pages',
			'read_private_posts',
			'remove_users',
			'resume_plugins',
			'resume_themes',
			'setup_network',
			'switch_themes',
			'unfiltered_html',
			'unfiltered_upload',
			'update_core',
			'update_languages',
			'update_plugins',
			'update_themes',
			'upgrade_network',
			'upload_files',
			'upload_plugins',
			'upload_themes',
			'view_site_health_checks',
		];

		$this->assertSame( [], array_values( array_diff( $knownWordpressCapabilities, array_keys( $definitions ) ) ) );
		foreach ( $definitions as $definition ) {
			$this->assertContains( $definition[ 'area' ], $provider->areaKeys() );
			$this->assertContains( $definition[ 'action' ], $provider->actionKeys() );
		}
	}

	public function testCapabilityGroupsClassifyCapabilitiesByAreaAndAction() :void {
		$groups = ( new CapabilityGroupProvider() )->group(
			[
				'read'              => true,
				'edit_posts'        => true,
				'upload_files'      => true,
				'wpm_manage_widget' => true,
			],
			[ 'read_post' => true ]
		);

		$this->assertSame(
			[ 'wordpress', 'third_party' ],
			array_column( $groups[ 'sources' ], 'key' )
		);
		$sources = array_column( $groups[ 'sources' ], null, 'key' );
		$this->assertSame( [ 'posts', 'media', 'general' ], array_column( $sources[ 'wordpress' ][ 'area' ], 'key' ) );
		$this->assertSame( [ 'read', 'write' ], array_column( $sources[ 'wordpress' ][ 'action' ], 'key' ) );
		$this->assertSame( [ 'third_party' ], array_column( $sources[ 'third_party' ][ 'area' ], 'key' ) );
		$this->assertSame( [ 'write' ], array_column( $sources[ 'third_party' ][ 'action' ], 'key' ) );

		$items = $this->capabilityItemsByName( $groups[ 'items' ] );
		$this->assertSame( [ 'source' => 'wordpress', 'area' => 'general', 'action' => 'read', 'type' => 'primitive', 'known' => true ], $this->capabilityItemSummary( $items[ 'read' ] ) );
		$this->assertSame( [ 'source' => 'wordpress', 'area' => 'posts', 'action' => 'write', 'type' => 'primitive', 'known' => true ], $this->capabilityItemSummary( $items[ 'edit_posts' ] ) );
		$this->assertSame( [ 'source' => 'wordpress', 'area' => 'media', 'action' => 'write', 'type' => 'primitive', 'known' => true ], $this->capabilityItemSummary( $items[ 'upload_files' ] ) );
		$this->assertSame( [ 'source' => 'wordpress', 'area' => 'posts', 'action' => 'read', 'type' => 'meta', 'known' => true ], $this->capabilityItemSummary( $items[ 'read_post' ] ) );
		$this->assertSame( [ 'source' => 'third_party', 'area' => 'third_party', 'action' => 'write', 'type' => 'primitive', 'known' => false ], $this->capabilityItemSummary( $items[ 'wpm_manage_widget' ] ) );
	}

	public function testCapabilityGroupsInferThirdPartyActionsDeterministically() :void {
		$groups = ( new CapabilityGroupProvider() )->group(
			[
				'view_widget'   => true,
				'add_widget'    => true,
				'delete_widget' => true,
				'manage_widget' => true,
			],
			[]
		);
		$items = $this->capabilityItemsByName( $groups[ 'items' ] );

		$this->assertSame( 'read', $items[ 'view_widget' ][ 'action' ] );
		$this->assertSame( 'write', $items[ 'add_widget' ][ 'action' ] );
		$this->assertSame( 'delete', $items[ 'delete_widget' ][ 'action' ] );
		$this->assertSame( 'write', $items[ 'manage_widget' ][ 'action' ] );
		foreach ( $items as $item ) {
			$this->assertSame( 'third_party', $item[ 'source' ] );
			$this->assertSame( 'third_party', $item[ 'area' ] );
			$this->assertFalse( $item[ 'known' ] );
		}
	}

	public function testCapabilityGroupsClassifyRegisteredMetaCapsWithDefinitions() :void {
		$defaultMetaCaps = ( new MetaCapabilityRegistry() )->registered();
		$groups = ( new CapabilityGroupProvider() )->group(
			[],
			$defaultMetaCaps + [ 'wpm_manage_meta' => true ]
		);
		$items = $this->capabilityItemsByName( $groups[ 'items' ] );

		foreach ( array_keys( $defaultMetaCaps ) as $capability ) {
			$this->assertArrayHasKey( $capability, $items );
			$this->assertSame( 'meta', $items[ $capability ][ 'type' ] );
			$this->assertSame( 'wordpress', $items[ $capability ][ 'source' ] );
			$this->assertTrue( $items[ $capability ][ 'known' ] );
		}
		$this->assertSame( 'third_party', $items[ 'wpm_manage_meta' ][ 'source' ] );
		$this->assertSame( 'third_party', $items[ 'wpm_manage_meta' ][ 'area' ] );
		$this->assertSame( 'write', $items[ 'wpm_manage_meta' ][ 'action' ] );
	}

	public function testCapabilityGroupsOrderAreaSectionsByVisibleCountAndItemsByActionThenName() :void {
		$groups = ( new CapabilityGroupProvider() )->group(
			[
				'level_10'      => true,
				'level_2'       => true,
				'level_1'       => true,
				'upload_files'  => true,
				'publish_posts' => true,
				'edit_posts'    => true,
				'delete_posts'  => true,
				'manage_options' => true,
				'read'          => true,
			],
			[]
		);

		$sources = array_column( $groups[ 'sources' ], null, 'key' );
		$this->assertSame( [ 'posts', 'general', 'media', 'legacy' ], array_column( $sources[ 'wordpress' ][ 'area' ], 'key' ) );
		$this->assertSame( [ 'read', 'write', 'delete' ], array_column( $sources[ 'wordpress' ][ 'action' ], 'key' ) );

		$sections = array_column( $sources[ 'wordpress' ][ 'area' ], null, 'key' );
		$this->assertSame( [ 'edit_posts', 'publish_posts', 'delete_posts' ], array_column( $sections[ 'posts' ][ 'items' ], 'name' ) );
		$this->assertSame( [ 'level_1', 'level_2', 'level_10' ], array_column( $sections[ 'legacy' ][ 'items' ], 'name' ) );
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
			'mandate_app_security_meta_capabilities',
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
		$_POST[ AdminScopeFormSecurity::ACTION_FIELD ] = 'save_scope';

		$this->adminPage()->handlePost();

		$this->assertSame( [], $this->scopeRepository()->all() );
	}

	public function testPluginBootRegistersGlobalHooksOnlyOutsideAdmin() :void {
		$GLOBALS[ 'wpm_test_is_admin' ] = false;

		Plugin::boot( $this->pluginFile() );

		$this->assertHookRegistered( 'application_password_did_authenticate', 'action', 20, 2 );
		$this->assertHookRegistered( 'user_has_cap', 'filter', PHP_INT_MAX, 4 );
		$this->assertHookRegistered( 'map_meta_cap', 'filter', PHP_INT_MAX, 4 );
		$this->assertHookRegistered( 'wp_delete_application_password', 'action', 10, 2 );
		$this->assertHookRegistered( ApplicationPasswordExpirationReaper::HOOK, 'action', 10, 1 );
		$this->assertArrayHasKey( $this->pluginFile(), $GLOBALS[ 'wpm_test_deactivation_hooks' ] );
		$this->assertNotFalse( wp_next_scheduled( ApplicationPasswordExpirationReaper::HOOK ) );

		$this->assertHookNotRegistered( 'admin_menu', 'action' );
		$this->assertHookNotRegistered( 'admin_init', 'action' );
		$this->assertHookNotRegistered( 'admin_enqueue_scripts', 'action' );
		$this->assertHookNotRegistered( 'manage_application-passwords-user_custom_column', 'action' );
		$this->assertHookNotRegistered( 'manage_application-passwords-user_custom_column_js_template', 'action' );
		$this->assertHookNotRegistered( 'manage_application-passwords-user_columns', 'filter' );
		$this->assertHookNotRegistered( 'plugin_action_links_'.PluginIdentity::MAIN_FILE, 'filter' );
	}

	public function testUnsupportedNoticeRegistersFullPrefixedCallback() :void {
		require dirname( __DIR__, 2 ).'/unsupported.php';

		$this->assertHookRegistered( 'admin_notices', 'action', 10, 1 );
		$this->assertHookRegistered( 'network_admin_notices', 'action', 10, 1 );
		$this->assertSame(
			'mandate_app_security_unsupported_notice',
			$GLOBALS[ 'wpm_test_actions' ][ 'admin_notices' ][ 10 ][ 0 ][ 'callback' ]
		);
		$this->assertSame(
			'mandate_app_security_unsupported_notice',
			$GLOBALS[ 'wpm_test_actions' ][ 'network_admin_notices' ][ 10 ][ 0 ][ 'callback' ]
		);
	}

	public function testPluginBootRegistersAdminHooksOnlyInsideAdmin() :void {
		$GLOBALS[ 'wpm_test_is_admin' ] = true;

		Plugin::boot( $this->pluginFile() );

		$this->assertHookRegistered( 'admin_menu', 'action', 10, 1 );
		$this->assertHookRegistered( 'manage_application-passwords-user_columns', 'filter', 10, 1 );
		$this->assertHookRegistered( 'manage_application-passwords-user_custom_column', 'action', 10, 2 );
		$this->assertHookRegistered( 'manage_application-passwords-user_custom_column_js_template', 'action', 10, 1 );
		$this->assertHookRegistered( 'plugin_action_links_'.PluginIdentity::MAIN_FILE, 'filter', 10, 1 );
		$this->assertHookNotRegistered( 'admin_init', 'action' );
	}

	public function testPluginAdminMenuRegistersPageLoadHook() :void {
		$GLOBALS[ 'wpm_test_is_admin' ] = true;

		Plugin::boot( $this->pluginFile() );
		do_action( 'admin_menu' );

		$page = $GLOBALS[ 'wpm_test_management_pages' ][ Plugin::MENU_SLUG ] ?? null;
		$this->assertIsArray( $page );
		$this->assertSame( 'read', $page[ 'capability' ] );
		$this->assertSame( 'tools_page_'.Plugin::MENU_SLUG, $page[ 'hook_suffix' ] );
		$this->assertHookRegistered( 'load-'.$page[ 'hook_suffix' ], 'action', 10, 1 );

		do_action( 'load-'.$page[ 'hook_suffix' ] );
		$this->assertHookRegistered( 'admin_enqueue_scripts', 'action', 10, 1 );
	}

	public function testPluginAdminMenuSkipsPageForUnavailableNonAdminOwner() :void {
		$this->seedAdminFixture();
		$this->actAsUser( 5 );
		$this->setApplicationPasswordsAvailableForUser( 5, false );
		$GLOBALS[ 'wpm_test_is_admin' ] = true;

		Plugin::boot( $this->pluginFile() );
		do_action( 'admin_menu' );

		$this->assertArrayNotHasKey( Plugin::MENU_SLUG, $GLOBALS[ 'wpm_test_management_pages' ] );
		$this->assertHookNotRegistered( 'load-tools_page_'.Plugin::MENU_SLUG, 'action' );
	}

	public function testAdminScopeAccessPolicyRespectsApplicationPasswordAvailabilityForOwners() :void {
		$this->seedAdminFixture();
		$this->actAsUser( 5 );
		$policy = new AdminScopeAccessPolicy();

		$this->assertTrue( $policy->canAccessPage() );
		$this->assertTrue( $policy->canManageUserScope( 5 ) );
		$this->assertSame( 5, $policy->selectedUserId( '9' ) );
		$this->assertTrue( $policy->canUseScopeShortcutForProfileUser( 5 ) );

		$this->setApplicationPasswordsAvailableForUser( 5, false );

		$this->assertFalse( $policy->canAccessPage() );
		$this->assertFalse( $policy->canManageUserScope( 5 ) );
		$this->assertSame( 0, $policy->selectedUserId( '9' ) );
		$this->assertFalse( $policy->canUseScopeShortcutForProfileUser( 5 ) );

		$GLOBALS[ 'wpm_test_current_user_id' ] = 1;
		$GLOBALS[ 'wpm_test_current_user_caps' ] = [ 'manage_options' => true ];

		$this->assertTrue( $policy->canAccessPage() );
		$this->assertTrue( $policy->canManageUserScope( 5 ) );
		$this->assertSame( 5, $policy->selectedUserId( '5' ) );
		$this->assertTrue( $policy->canUseScopeShortcutForProfileUser( 5 ) );
	}

	public function testApplicationPasswordScopeColumnOrdersScopeBeforeRevoke() :void {
		$scopeColumn = new ApplicationPasswordScopeColumn();

		$this->assertSame( 'mandate_app_security_scope', ApplicationPasswordScopeColumn::COLUMN_KEY );

		$columns = $scopeColumn->addColumn(
			[
				'name'   => 'Name',
				'revoke' => 'Revoke',
			]
		);

		$this->assertSame( [ 'name', ApplicationPasswordScopeColumn::COLUMN_KEY, 'revoke' ], array_keys( $columns ) );

		$columns = $scopeColumn->addColumn( [ 'name' => 'Name' ] );

		$this->assertSame( [ 'name', ApplicationPasswordScopeColumn::COLUMN_KEY ], array_keys( $columns ) );
	}

	public function testApplicationPasswordScopeColumnRequiresShortcutAccess() :void {
		$GLOBALS[ 'wpm_test_current_user_caps' ] = [];
		$scopeColumn = new ApplicationPasswordScopeColumn();

		$this->assertSame( [ 'revoke' => 'Revoke' ], $scopeColumn->addColumn( [ 'revoke' => 'Revoke' ] ) );
		$GLOBALS[ 'user_id' ] = 5;

		$html = $this->captureOutput(
			fn() => $scopeColumn->renderColumn( ApplicationPasswordScopeColumn::COLUMN_KEY, [ 'uuid' => self::UUID ] )
		);

		$this->assertSame( '', $html );
	}

	public function testApplicationPasswordScopeColumnAllowsOwnersOnOwnProfileOnly() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_current_user_id' ] = 5;
		$GLOBALS[ 'wpm_test_current_user_caps' ] = [ 'read' => true ];
		$GLOBALS[ 'user_id' ] = 5;
		$scopeColumn = new ApplicationPasswordScopeColumn();

		$columns = $scopeColumn->addColumn( [ 'revoke' => 'Revoke' ] );
		$this->assertSame( [ ApplicationPasswordScopeColumn::COLUMN_KEY, 'revoke' ], array_keys( $columns ) );
		$html = $this->captureOutput(
			fn() => $scopeColumn->renderColumn( ApplicationPasswordScopeColumn::COLUMN_KEY, [ 'uuid' => self::UUID ] )
		);
		$this->assertSame( self::UUID, $this->queryParamFromHref( $this->actionLinkHref( $html ), 'app_password_uuid' ) );

		$GLOBALS[ 'user_id' ] = 9;
		$this->assertSame( [ 'revoke' => 'Revoke' ], $scopeColumn->addColumn( [ 'revoke' => 'Revoke' ] ) );
		$this->assertSame(
			'',
			$this->captureOutput( fn() => $scopeColumn->renderColumn( ApplicationPasswordScopeColumn::COLUMN_KEY, [ 'uuid' => self::UUID ] ) )
		);
	}

	public function testApplicationPasswordScopeColumnHidesOwnerShortcutWhenApplicationPasswordsUnavailable() :void {
		$this->seedAdminFixture();
		$this->actAsUser( 5 );
		$this->setApplicationPasswordsAvailableForUser( 5, false );
		$GLOBALS[ 'user_id' ] = 5;
		$scopeColumn = new ApplicationPasswordScopeColumn();

		$this->assertSame( [ 'revoke' => 'Revoke' ], $scopeColumn->addColumn( [ 'revoke' => 'Revoke' ] ) );
		$this->assertSame(
			'',
			$this->captureOutput( fn() => $scopeColumn->renderColumn( ApplicationPasswordScopeColumn::COLUMN_KEY, [ 'uuid' => self::UUID ] ) )
		);
		$this->assertSame(
			'',
			$this->captureOutput( fn() => $scopeColumn->renderColumnJsTemplate( ApplicationPasswordScopeColumn::COLUMN_KEY ) )
		);
	}

	public function testApplicationPasswordScopeColumnRendersSanitizedDeepLink() :void {
		$GLOBALS[ 'user_id' ] = 5;
		$scopeColumn = new ApplicationPasswordScopeColumn();

		$html = $this->captureOutput(
			fn() => $scopeColumn->renderColumn(
				ApplicationPasswordScopeColumn::COLUMN_KEY,
				[
					'uuid'     => strtoupper( self::UUID ),
					'name'     => 'Client',
					'password' => 'plain-secret',
				]
			)
		);

		$this->assertStringNotContainsString( 'Client', $html );
		$this->assertStringNotContainsString( 'plain-secret', $html );

		$href = $this->actionLinkHref( $html );
		$this->assertSame( 'https', parse_url( $href, PHP_URL_SCHEME ) );
		$this->assertSame( 'example.test', parse_url( $href, PHP_URL_HOST ) );
		$this->assertSame( '/wp-admin/tools.php', parse_url( $href, PHP_URL_PATH ) );

		$query = parse_url( $href, PHP_URL_QUERY );
		$this->assertIsString( $query );
		parse_str( $query, $params );
		$this->assertSame(
			[
				'page'              => Plugin::MENU_SLUG,
				'user_id'           => '5',
				'app_password_uuid' => self::UUID,
			],
			$params
		);
	}

	public function testApplicationPasswordScopeColumnRendersNoActiveLinkForInvalidReferences() :void {
		$scopeColumn = new ApplicationPasswordScopeColumn();

		unset( $GLOBALS[ 'user_id' ] );
		$html = $this->captureOutput(
			fn() => $scopeColumn->renderColumn( ApplicationPasswordScopeColumn::COLUMN_KEY, [ 'uuid' => self::UUID ] )
		);
		$this->assertSame( '&mdash;', $html );

		$GLOBALS[ 'user_id' ] = 5;
		$html = $this->captureOutput(
			fn() => $scopeColumn->renderColumn( ApplicationPasswordScopeColumn::COLUMN_KEY, [ 'uuid' => 'not-a-uuid' ] )
		);
		$this->assertSame( '&mdash;', $html );
	}

	public function testApplicationPasswordScopeColumnRendersJsTemplateDeepLink() :void {
		$GLOBALS[ 'user_id' ] = 5;
		$scopeColumn = new ApplicationPasswordScopeColumn();

		$this->assertSame(
			'',
			$this->captureOutput( fn() => $scopeColumn->renderColumnJsTemplate( 'name' ) )
		);

		$html = $this->captureOutput(
			fn() => $scopeColumn->renderColumnJsTemplate( ApplicationPasswordScopeColumn::COLUMN_KEY )
		);

		$this->assertStringContainsString( 'page='.Plugin::MENU_SLUG, $html );
		$this->assertStringContainsString( 'user_id=5', $html );
		$this->assertStringContainsString( 'app_password_uuid={{ data.uuid }}', $html );
		$this->assertStringContainsString( 'Restrict Scope', $html );
		$this->assertStringNotContainsString( 'data.name', $html );
		$this->assertStringNotContainsString( 'plain-secret', $html );

		unset( $GLOBALS[ 'user_id' ] );
		$this->assertSame(
			'&mdash;',
			$this->captureOutput( fn() => $scopeColumn->renderColumnJsTemplate( ApplicationPasswordScopeColumn::COLUMN_KEY ) )
		);
	}

	public function testAdminPagePrependsSettingsPluginActionLink() :void {
		$GLOBALS[ 'wpm_test_is_admin' ] = true;
		Plugin::boot( $this->pluginFile() );

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

	public function testPluginAdminAssetsEnqueueOnlyForLoadedPageHookAndExistingDistFiles() :void {
		$root = sys_get_temp_dir().'/mandate-admin-assets-'.bin2hex( random_bytes( 4 ) );
		$dist = $root.'/assets/dist';
		$pluginFile = $root.'/'.PluginIdentity::MAIN_FILE;
		if ( !mkdir( $dist, 0777, true ) && !is_dir( $dist ) ) {
			throw new RuntimeException( 'Failed to create admin asset fixture directory.' );
		}
		file_put_contents( $pluginFile, "<?php\n" );
		file_put_contents( $dist.'/admin-page.css', "body{}\n" );

		try {
			$GLOBALS[ 'wpm_test_is_admin' ] = true;
			Plugin::boot( $pluginFile );
			do_action( 'admin_menu' );

			$pageHook = $GLOBALS[ 'wpm_test_management_pages' ][ Plugin::MENU_SLUG ][ 'hook_suffix' ];
			do_action( 'load-'.$pageHook );
			do_action( 'admin_enqueue_scripts', 'dashboard_page_not_mandate' );
			$this->assertSame( [], $GLOBALS[ 'wpm_test_enqueued_styles' ] );
			$this->assertSame( [], $GLOBALS[ 'wpm_test_enqueued_scripts' ] );

			do_action( 'admin_enqueue_scripts', $pageHook );
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

	public function testAdminPostRequiresLoggedInPageAccessBeforeMutation() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_current_user_id' ] = 0;
		$GLOBALS[ 'wpm_test_current_user_caps' ] = [];
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true );

		$this->assertThrowsRuntimeException(
			fn() => $this->adminPage()->handlePost()
		);
		$this->assertSame( [], $this->scopeRepository()->all() );
	}

	public function testAdminPostRequiresReadPageAccessBeforeMutation() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_current_user_id' ] = 5;
		$GLOBALS[ 'wpm_test_current_user_caps' ] = [];
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true );

		$this->assertThrowsRuntimeException(
			fn() => $this->adminPage()->handlePost()
		);
		$this->assertSame( [], $this->scopeRepository()->all() );
	}

	public function testNonAdminPostSavesOwnUnlockedScopeOnly() :void {
		$this->seedAdminFixture();
		$this->actAsUser( 5 );
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true, true );

		$repository = $this->scopeRepository();
		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $location ) );
		$this->assertNotNull( $record );
		$this->assertSame( [ 'read' => true ], $record[ 'allowed_caps' ] );
		$this->assertFalse( $record[ 'admin_locked' ] );
		$this->assertSame( 5, $record[ 'updated_by' ] );
	}

	public function testNonAdminPostRejectsOwnScopeWhenApplicationPasswordsUnavailableWithoutMutation() :void {
		$this->seedAdminFixture();
		$this->actAsUser( 5 );
		$this->setApplicationPasswordsAvailableForUser( 5, false );
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true );

		$repository = $this->scopeRepository();
		$this->assertThrowsRuntimeException(
			fn() => $this->adminPage( $repository )->handlePost()
		);

		$this->assertSame( [], $repository->all() );
	}

	public function testNonAdminPostRejectsForgedOtherUserScopeWithoutMutation() :void {
		$this->seedAdminFixture();
		$this->actAsUser( 5 );
		$this->submitScopePost( 'save_scope', 9, self::OTHER_UUID, [ 'read' ], [], true );

		$repository = $this->scopeRepository();
		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 'invalid', $this->redirectMessage( $location ) );
		$this->assertSame( [], $repository->all() );
	}

	public function testNonAdminPostRejectsOwnAdminLockedScopeWithoutMutation() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1, null, true, true );
		$before = $repository->findForUser( 5, self::UUID );
		$this->actAsUser( 5 );

		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read', 'upload_files' ], [], true );
		$saveLocation = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$this->assertSame( 'locked', $this->redirectMessage( $saveLocation ) );
		$this->assertSame( $before, $repository->findForUser( 5, self::UUID ) );

		$this->submitScopePost( 'clear_scope', 5, self::UUID, [], [], true );
		$clearLocation = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$this->assertSame( 'locked', $this->redirectMessage( $clearLocation ) );
		$this->assertSame( $before, $repository->findForUser( 5, self::UUID ) );
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
		$_POST[ 'capability_grouping_mode' ] = 'action';
		$_POST[ 'capability_source' ] = 'third_party';

		$repository = $this->scopeRepository();
		$this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );
		$stored = $this->storedScopes()[ self::UUID ];

		$this->assertNotNull( $record );
		$this->assertTrue( $record[ 'capabilities_restricted' ] );
		$this->assertSame(
			[ 'read' => true, 'upload_files' => true ],
			$record[ 'allowed_caps' ]
		);
		$this->assertSame( [ 'edit_post' => true ], $record[ 'allowed_meta_caps' ] );
		$this->assertNull( $record[ 'expires_on' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
		$this->assertArrayNotHasKey( 'grouping', $stored );
		$this->assertArrayNotHasKey( 'source', $stored );
		$this->assertArrayNotHasKey( 'area', $stored );
		$this->assertArrayNotHasKey( 'action', $stored );
		$this->assertSame( false, $GLOBALS[ 'wpm_test_autoload' ][ PluginOptionsRepository::OPTION_NAME ] );
	}

	public function testAdminPostCanSaveAndResetAnotherUsersScope() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$this->submitScopePost( 'save_scope', 9, self::OTHER_UUID, [ 'read' ], [], true );

		$saveLocation = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 9, self::OTHER_UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $saveLocation ) );
		$this->assertNotNull( $record );
		$this->assertSame( [ 'read' => true ], $record[ 'allowed_caps' ] );

		$this->submitScopePost( 'clear_scope', 9, self::OTHER_UUID, [], [], true );
		$resetLocation = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 'reset', $this->redirectMessage( $resetLocation ) );
		$this->assertNull( $repository->findForUser( 9, self::OTHER_UUID ) );
	}

	public function testAdminPostCanManageUserWhenOwnerApplicationPasswordsUnavailable() :void {
		$this->seedAdminFixture();
		$this->setApplicationPasswordsAvailableForUser( 5, false );
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true );

		$repository = $this->scopeRepository();
		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $location ) );
		$this->assertNotNull( $record );
		$this->assertSame( [ 'read' => true ], $record[ 'allowed_caps' ] );
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

	public function testAdminPostCanPersistAndRemoveLockOnlyScope() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$this->submitScopePost(
			'save_scope',
			5,
			self::UUID,
			[ 'read', 'edit_posts', 'upload_files' ],
			array_keys( ( new MetaCapabilityRegistry() )->registered() ),
			true,
			true
		);

		$saveLocation = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $saveLocation ) );
		$this->assertNotNull( $record );
		$this->assertFalse( $record[ 'capabilities_restricted' ] );
		$this->assertTrue( $record[ 'admin_locked' ] );

		$this->submitScopePost(
			'save_scope',
			5,
			self::UUID,
			[ 'read', 'edit_posts', 'upload_files' ],
			array_keys( ( new MetaCapabilityRegistry() )->registered() ),
			true
		);

		$unlockLocation = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 'reset', $this->redirectMessage( $unlockLocation ) );
		$this->assertNull( $repository->findForUser( 5, self::UUID ) );
	}

	public function testAdminPostIgnoresAdminLockForAdministratorCapableTarget() :void {
		$this->seedAdminFixture();
		$this->makeFixtureUserAdministratorCapable( 9 );
		$this->submitScopePost( 'save_scope', 9, self::OTHER_UUID, [ 'read' ], [], true, true );

		$repository = $this->scopeRepository();
		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 9, self::OTHER_UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $location ) );
		$this->assertNotNull( $record );
		$this->assertSame( [ 'read' => true ], $record[ 'allowed_caps' ] );
		$this->assertFalse( $record[ 'admin_locked' ] );
		$this->assertSame( [ 'wpm_admin' ], $record[ 'roles_at_update' ] );
	}

	public function testAdminPostCanUnlockRestrictedScopeWithoutChangingRestrictions() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1, null, true, true );
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], true, false );

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $location ) );
		$this->assertNotNull( $record );
		$this->assertSame( [ 'read' => true ], $record[ 'allowed_caps' ] );
		$this->assertFalse( $record[ 'admin_locked' ] );
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

	public function testAdminRenderMovesCapabilityDescriptionTooltipsToInfoIcons() :void {
		$this->seedAdminFixture();
		$GLOBALS[ 'wpm_test_roles' ]->roles[ 'wpm_editor' ][ 'wpm_manage_widget' ] = true;

		$html = $this->renderAdminPage( $this->scopeRepository() );

		$describedCode = $this->capabilityCodeAttributes( $html, 'upload_files' );
		$this->assertArrayNotHasKey( 'data-wpm-tooltip', $describedCode );
		$this->assertArrayNotHasKey( 'data-wpm-tooltip-text', $describedCode );
		$this->assertArrayNotHasKey( 'tabindex', $describedCode );

		$describedInfo = $this->capabilityInfoAttributes( $html, 'upload_files' );
		$this->assertArrayHasKey( 'data-wpm-tooltip', $describedInfo );
		$this->assertArrayHasKey( 'data-wpm-tooltip-text', $describedInfo );
		$this->assertNotSame( '', $describedInfo[ 'data-wpm-tooltip-text' ] );
		$this->assertNotSame( '', $describedInfo[ 'aria-label' ] );

		$custom = $this->capabilityCodeAttributes( $html, 'wpm_manage_widget' );
		$this->assertArrayNotHasKey( 'data-wpm-tooltip', $custom );
		$this->assertArrayNotHasKey( 'data-wpm-tooltip-text', $custom );
		$this->assertArrayNotHasKey( 'tabindex', $custom );
		$this->assertSame( 0, $this->capabilityInfoCount( $html, 'wpm_manage_widget' ) );
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
		$this->assertArrayNotHasKey( 'password_summary', $selectionForm );
		$this->assertSame( 'mandate-password-info', $selectionForm[ 'password_info' ][ 'container_id' ] );
		$this->assertSame( 'inside', $selectionForm[ 'password_info' ][ 'title_placement' ] );
		$this->assertSame( [ 'UUID', 'Created', 'Last Used' ], array_column( $selectionForm[ 'password_info' ][ 'details' ], 'label' ) );
		$this->assertFalse( in_array( 'Name', array_column( $selectionForm[ 'password_info' ][ 'details' ], 'label' ), true ) );
		$this->assertFalse( in_array( 'App ID', array_column( $selectionForm[ 'password_info' ][ 'details' ], 'label' ), true ) );
		$this->assertFalse( in_array( 'Restricted Scope', array_column( $selectionForm[ 'password_info' ][ 'details' ], 'label' ), true ) );
		$this->assertSame( 'mandate-rules-summary', $selectionForm[ 'mandate_rules' ][ 'container_id' ] );
		$this->assertSame( 'outside', $selectionForm[ 'mandate_rules' ][ 'title_placement' ] );
		$this->assertSame( [ 'Restricted Scope', 'Expiration Date', 'Lock This Scope' ], array_slice( array_column( $selectionForm[ 'mandate_rules' ][ 'details' ], 'label' ), 0, 3 ) );
		$adminLockDetail = $selectionForm[ 'mandate_rules' ][ 'details' ][ 2 ];
		$this->assertSame( 'admin_lock', $adminLockDetail[ 'kind' ] );
		$this->assertSame( 'Lock This Scope', $adminLockDetail[ 'label' ] );
		$this->assertSame( 'admin_locked', $adminLockDetail[ 'input' ][ 'name' ] );
		$this->assertSame( 'mandate-scope-form', $adminLockDetail[ 'input' ][ 'form' ] );
		$this->assertFalse( $adminLockDetail[ 'input' ][ 'checked' ] );
		$this->assertFalse( $adminLockDetail[ 'input' ][ 'disabled' ] );

		$scopeForm = $data[ 'vars' ][ 'scope_form' ];
		$this->assertSame( 'mandate-scope-form', $scopeForm[ 'id' ] );
		$this->assertSame( self::UUID, $scopeForm[ 'uuid' ] );
		$this->assertArrayNotHasKey( 'admin_lock', $scopeForm );
		$this->assertArrayNotHasKey( 'tabs', $scopeForm );
		$this->assertArrayNotHasKey( 'panels', $scopeForm );
		$this->assertSame( 'wordpress', $scopeForm[ 'grouping' ][ 'default_source' ] );
		$this->assertSame( 'area', $scopeForm[ 'grouping' ][ 'default_mode' ] );
		$this->assertSame( [ 'area', 'action' ], array_column( $scopeForm[ 'grouping' ][ 'modes' ], 'key' ) );
		$this->assertSame( [ 'wordpress', 'third_party' ], array_column( $scopeForm[ 'source_tabs' ], 'key' ) );
		$this->assertSame( [ 'wordpress', 'third_party' ], array_column( $scopeForm[ 'source_panels' ], 'key' ) );
		$this->assertSame( 14, $scopeForm[ 'source_tabs' ][ 0 ][ 'count' ] );
		$this->assertSame( 0, $scopeForm[ 'source_tabs' ][ 1 ][ 'count' ] );
		$this->assertArrayNotHasKey( 'label', $scopeForm[ 'source_panels' ][ 0 ] );
		$this->assertArrayNotHasKey( 'count', $scopeForm[ 'source_panels' ][ 0 ] );
		$this->assertSame(
			[
				'mandate-wordpress-area-posts-capabilities',
				'mandate-wordpress-area-pages-capabilities',
				'mandate-wordpress-area-taxonomy-capabilities',
				'mandate-wordpress-area-users-capabilities',
				'mandate-wordpress-area-media-capabilities',
				'mandate-wordpress-area-general-capabilities',
			],
			array_column( $scopeForm[ 'source_panels' ][ 0 ][ 'section_index' ], 'target_id' )
		);
		$this->assertFalse( $scopeForm[ 'actions' ][ 0 ][ 'disabled' ] );
		$this->assertSame( 'unlocked', $scopeForm[ 'admin_lock_status' ] );

		$groupingConfig = json_decode( $scopeForm[ 'grouping' ][ 'config_json' ], true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( 'wordpress', $groupingConfig[ 'defaultSource' ] );
		$this->assertSame( 'area', $groupingConfig[ 'defaultMode' ] );
		$this->assertSame( [ 'wordpress', 'third_party' ], array_column( $groupingConfig[ 'sources' ], 'key' ) );
		$configSources = array_column( $groupingConfig[ 'sources' ], null, 'key' );
		$this->assertArrayNotHasKey( 'label', $configSources[ 'wordpress' ] );
		$this->assertSame( [ 'posts', 'pages', 'taxonomy', 'users', 'media', 'general' ], array_column( $configSources[ 'wordpress' ][ 'modes' ][ 'area' ][ 'sections' ], 'key' ) );
		$this->assertSame( [ 'read', 'write', 'delete' ], array_column( $configSources[ 'wordpress' ][ 'modes' ][ 'action' ][ 'sections' ], 'key' ) );
		$this->assertSame( [], $configSources[ 'third_party' ][ 'modes' ][ 'area' ][ 'sections' ] );
		$configSections = array_column( $configSources[ 'wordpress' ][ 'modes' ][ 'area' ][ 'sections' ], null, 'key' );
		$this->assertSame(
			[ 'meta:read_post', 'meta:edit_post', 'primitive:edit_posts', 'meta:delete_post' ],
			$configSections[ 'posts' ][ 'itemKeys' ]
		);
		$this->assertSame( 'checked', $configSections[ 'posts' ][ 'bulk_actions' ][ 'select_all' ][ 'state' ] );
		$this->assertFalse( $configSections[ 'posts' ][ 'bulk_actions' ][ 'select_all' ][ 'disabled' ] );

		$uploadFiles = $this->capabilityItemFromViewData( $data, 'upload_files' );
		$this->assertSame( 'primitive:upload_files', $uploadFiles[ 'item_key' ] );
		$this->assertSame( 'mandate-capability-primitive-upload_files', $uploadFiles[ 'input_id' ] );
		$this->assertSame( 'allowed_caps', $uploadFiles[ 'field_name' ] );
		$this->assertSame( 'primitive', $uploadFiles[ 'type' ] );
		$this->assertSame( 'wordpress', $uploadFiles[ 'source' ] );
		$this->assertSame( 'media', $uploadFiles[ 'area' ] );
		$this->assertSame( 'write', $uploadFiles[ 'action' ] );
		$this->assertSame( 'Write', $uploadFiles[ 'action_label' ] );
		$this->assertSame( 'W', $uploadFiles[ 'action_abbreviation' ] );
		$this->assertTrue( $uploadFiles[ 'checked' ] );
		$this->assertFalse( $uploadFiles[ 'disabled' ] );
		$this->assertTrue( $uploadFiles[ 'has_tooltip' ] );
		$this->assertIsString( $uploadFiles[ 'tooltip_text' ] );
		$this->assertIsString( $uploadFiles[ 'tooltip_aria_label' ] );

		$sourceSections = array_column( $scopeForm[ 'source_panels' ][ 0 ][ 'sections' ], null, 'id' );
		$this->assertSame( 'checked', $sourceSections[ 'mandate-wordpress-area-posts-capabilities' ][ 'bulk_actions' ][ 'select_all' ][ 'state' ] );
		$this->assertFalse( $sourceSections[ 'mandate-wordpress-area-posts-capabilities' ][ 'bulk_actions' ][ 'select_all' ][ 'disabled' ] );
	}

	public function testAdminPageViewDataBuilderRestrictsNonAdminSelectionToCurrentUser() :void {
		$this->seedAdminFixture();
		$this->actAsUser( 5 );
		$_GET = [
			'page'              => Plugin::MENU_SLUG,
			'user_id'           => '9',
			'app_password_uuid' => self::OTHER_UUID,
		];

		$data = $this->adminPageViewDataBuilder( $this->scopeRepository() )->build();
		$document = $this->documentFromHtml( $data[ 'trustedHtml' ][ 'user_dropdown' ] );
		$xpath = new DOMXPath( $document );

		$this->assertSame( 5, $data[ 'vars' ][ 'selection_form' ][ 'selected_user_id' ] );
		$this->assertSame( self::UUID, $data[ 'vars' ][ 'selection_form' ][ 'selected_uuid' ] );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//select[@id="mandate-user" and @name="user_id" and @disabled="disabled"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//option[@value="5" and @selected="selected"]' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//option[@value="9"]' ) );
	}

	public function testAdminPageRenderShowsLockedOwnerScopeAsReadOnly() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1, null, true, true );
		$this->actAsUser( 5 );

		$xpath = new DOMXPath( $this->documentFromHtml( $this->renderAdminPage( $repository ) ) );

		$this->assertSame( 1, $this->nodeCount( $xpath, '//*[@id="mandate-scope-form" and @data-wpm-admin-lock-status="locked"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//input[@name="allowed_caps[]" and @value="read" and @disabled="disabled"]' ) );
		$this->assertSame( 4, $this->nodeCount( $xpath, '//*[@data-wpm-select-panel and @disabled="disabled"]' ) );
		$this->assertSame( 12, $this->nodeCount( $xpath, '//*[@data-wpm-select-section and @disabled="disabled"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//*[@data-wpm-expiration-input and @disabled="disabled"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//button[@name="'.AdminScopeFormSecurity::ACTION_FIELD.'" and @value="save_scope" and @disabled="disabled"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//button[@name="'.AdminScopeFormSecurity::ACTION_FIELD.'" and @value="clear_scope" and @disabled="disabled"]' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//input[@name="admin_locked"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//*[@id="mandate-rules-summary"]//dt[normalize-space(.) = "Lock This Scope"]/following-sibling::dd[1][not(.//input)]' ) );
	}

	public function testAdminPageRenderShowsAdminLockControlForAdminsOnly() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [], [], [ 'wpm_editor' ], 1, null, false, true );

		$adminXpath = new DOMXPath( $this->documentFromHtml( $this->renderAdminPage( $repository ) ) );
		$this->assertSame( 1, $this->nodeCount( $adminXpath, '//*[@id="mandate-scope-form" and @data-wpm-admin-lock-status="locked"]' ) );
		$this->assertSame( 1, $this->nodeCount( $adminXpath, '//*[@id="mandate-rules-summary"]//input[@data-wpm-admin-lock-input and @name="admin_locked" and @value="1" and @form="mandate-scope-form" and @checked="checked"]' ) );
		$this->assertSame( 0, $this->nodeCount( $adminXpath, '//*[@id="mandate-scope-form"]//input[@name="admin_locked"]' ) );
		$this->assertSame( 0, $this->nodeCount( $adminXpath, '//input[@name="admin_locked" and @disabled="disabled"]' ) );

		$this->actAsUser( 5 );
		$userXpath = new DOMXPath( $this->documentFromHtml( $this->renderAdminPage( $repository ) ) );
		$this->assertSame( 0, $this->nodeCount( $userXpath, '//input[@name="admin_locked"]' ) );
	}

	public function testAdminPageViewDataBuilderDisablesAdminLockForAdministratorCapablePasswordOwner() :void {
		$this->seedAdminFixture();
		$this->makeFixtureUserAdministratorCapable( 9 );
		$repository = $this->scopeRepository();
		$repository->save( self::OTHER_UUID, 9, [ 'read' => true ], [], [ 'wpm_admin' ], 1, null, true, true );
		$_GET = [
			'page'              => Plugin::MENU_SLUG,
			'user_id'           => '9',
			'app_password_uuid' => self::OTHER_UUID,
		];

		$data = $this->adminPageViewDataBuilder( $repository )->build();
		$scopeForm = $data[ 'vars' ][ 'scope_form' ];
		$adminLockDetail = $data[ 'vars' ][ 'selection_form' ][ 'mandate_rules' ][ 'details' ][ 2 ];

		$this->assertSame( 'unlocked', $scopeForm[ 'admin_lock_status' ] );
		$this->assertSame( 'admin_lock', $adminLockDetail[ 'kind' ] );
		$this->assertFalse( $adminLockDetail[ 'input' ][ 'checked' ] );
		$this->assertTrue( $adminLockDetail[ 'input' ][ 'disabled' ] );
	}

	public function testAdminPageRenderPlacesMandateRulesTitleOutsideSummaryCard() :void {
		$this->seedAdminFixture();

		$xpath = new DOMXPath( $this->documentFromHtml( $this->renderAdminPage( $this->scopeRepository() ) ) );

		$this->assertSame( 3, $this->nodeCount( $xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " mandate-field-title ")]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//*[@id="mandate-rules-summary-title" and contains(concat(" ", normalize-space(@class), " "), " mandate-field-title ")]' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//*[@id="mandate-rules-summary"]//*[@id="mandate-rules-summary-title"]' ) );
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
		$adminLockDetail = $data[ 'vars' ][ 'selection_form' ][ 'mandate_rules' ][ 'details' ][ 2 ];

		$this->assertTrue( $scopeForm[ 'super_admin_notice' ][ 'is_visible' ] );
		$this->assertTrue( $scopeForm[ 'actions' ][ 0 ][ 'disabled' ] );
		$this->assertFalse( $scopeForm[ 'actions' ][ 1 ][ 'disabled' ] );
		$this->assertSame( 'admin_lock', $adminLockDetail[ 'kind' ] );
		$this->assertTrue( $adminLockDetail[ 'input' ][ 'disabled' ] );
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
		$summary = $data[ 'vars' ][ 'selection_form' ][ 'mandate_rules' ];
		$scopeDetails = $summary[ 'details' ];

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
			'<input type="hidden" id="custom-save-nonce" name="custom_save_nonce" value="abc" onclick="evil()" />'
			.'<input type="text" name="bad" value="bad" />'
			.'<select name="bad"><option value="bad">Bad</option></select>'
		);
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );

		$this->assertSame( 1, $this->nodeCount( $xpath, '//input' ) );
		$input = $this->firstElement( $xpath, '//input' );
		$this->assertSame( 'hidden', $input->getAttribute( 'type' ) );
		$this->assertSame( 'custom-save-nonce', $input->getAttribute( 'id' ) );
		$this->assertSame( 'custom_save_nonce', $input->getAttribute( 'name' ) );
		$this->assertSame( 'abc', $input->getAttribute( 'value' ) );
		$this->assertSame( '', $input->getAttribute( 'onclick' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//select|//option' ) );
		$this->assertFalse( str_contains( $html, 'Bad' ) );
	}

	public function testAdminScopeFormSecurityEmitsOnlyActionNonceFields() :void {
		$formSecurity = new AdminScopeFormSecurity( new AdminTrustedHtmlSanitizer() );
		$html = $formSecurity->nonceFields( 5, self::UUID );
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );

		$this->assertSame( 'mandate_app_security_action', AdminScopeFormSecurity::ACTION_FIELD );
		$this->assertSame(
			'mandate_app_security_scope:save_scope:5:'.self::UUID,
			$formSecurity->nonceAction( AdminScopeFormSecurity::ACTION_SAVE, 5, self::UUID )
		);
		$this->assertSame(
			'mandate_app_security_save_scope_nonce',
			$formSecurity->nonceName( AdminScopeFormSecurity::ACTION_SAVE )
		);
		$this->assertSame( 2, $this->nodeCount( $xpath, '//input' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//input[@type="hidden" and @id="mandate_app_security_save_scope_nonce" and @name="mandate_app_security_save_scope_nonce"]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//input[@type="hidden" and @id="mandate_app_security_clear_scope_nonce" and @name="mandate_app_security_clear_scope_nonce"]' ) );
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

	public function testAdminRenderPreservesLateEscapedMachineOwnedDataContracts() :void {
		$this->seedAdminFixture();

		$xpath = new DOMXPath( $this->documentFromHtml( $this->renderAdminPage( $this->scopeRepository() ) ) );
		$groups = $this->firstElement( $xpath, '//*[@data-wpm-capability-groups]' );
		$config = json_decode(
			$groups->getAttribute( 'data-wpm-capability-grouping-config' ),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		$this->assertSame( 'wordpress', $config[ 'defaultSource' ] );
		$this->assertSame( 'area', $config[ 'defaultMode' ] );
		$this->assertIsArray( $config[ 'sources' ] );
		$this->assertNotSame( [], $config[ 'sources' ] );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//*[@data-wpm-selection-form]' ) );
		$this->assertGreaterThan( 0, $this->nodeCount( $xpath, '//*[@data-wpm-capability-item]' ) );
		$this->assertGreaterThan( 0, $this->nodeCount( $xpath, '//*[@data-wpm-select-panel and @data-wpm-select-state]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//*[@data-wpm-expiration-summary and @aria-controls]' ) );
	}

	public function testAdminTemplateAllowedHtmlStripsDisallowedMarkupAndPreservesAllowedControls() :void {
		$html = wp_kses(
			'<form method="post" action="https://example.test" data-wpm-selection-form onclick="evil()">'
			.'<input type="hidden" name="'.AdminScopeFormSecurity::ACTION_FIELD.'" value="save_scope" data-wpm-admin-lock-input />'
			.'<script>alert(1)</script><span data-wpm-select-panel data-wpm-select-state="checked">ok</span>'
			.'</form>',
			( new AdminTemplateRenderer() )->allowedAdminHtml()
		);
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );

		$this->assertSame( 1, $this->nodeCount( $xpath, '//form[@method="post" and @data-wpm-selection-form]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//input[@type="hidden" and @name="'.AdminScopeFormSecurity::ACTION_FIELD.'" and @data-wpm-admin-lock-input]' ) );
		$this->assertSame( 1, $this->nodeCount( $xpath, '//span[@data-wpm-select-panel and @data-wpm-select-state="checked"]' ) );
		$this->assertSame( 0, $this->nodeCount( $xpath, '//script|//@onclick' ) );
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
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1, null, true, true );
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

	public function testPluginBootAdminTableHooksUseScopeColumnBehavior() :void {
		$GLOBALS[ 'wpm_test_is_admin' ] = true;
		$GLOBALS[ 'user_id' ] = 5;

		Plugin::boot( $this->pluginFile() );

		$columns = apply_filters( 'manage_application-passwords-user_columns', [ 'revoke' => 'Revoke' ] );
		$this->assertSame( [ ApplicationPasswordScopeColumn::COLUMN_KEY, 'revoke' ], array_keys( $columns ) );

		$html = $this->captureOutput(
			fn() => do_action(
				'manage_application-passwords-user_custom_column',
				ApplicationPasswordScopeColumn::COLUMN_KEY,
				[ 'uuid' => self::UUID ]
			)
		);
		$this->assertSame( self::UUID, $this->queryParamFromHref( $this->actionLinkHref( $html ), 'app_password_uuid' ) );
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

	private function assertHookRegistered( string $hookName, string $type, int $priority, int $acceptedArgs ) :void {
		$storage = $type === 'filter' ? $GLOBALS[ 'wpm_test_filters' ] : $GLOBALS[ 'wpm_test_actions' ];
		$callbacks = $storage[ $hookName ][ $priority ] ?? [];
		$this->assertNotSame( [], $callbacks, $hookName );
		$this->assertSame( $acceptedArgs, (int)$callbacks[ 0 ][ 'accepted_args' ], $hookName );
		$this->assertIsCallable( $callbacks[ 0 ][ 'callback' ], $hookName );
	}

	private function assertHookNotRegistered( string $hookName, string $type ) :void {
		$storage = $type === 'filter' ? $GLOBALS[ 'wpm_test_filters' ] : $GLOBALS[ 'wpm_test_actions' ];
		$this->assertArrayNotHasKey( $hookName, $storage );
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
		$GLOBALS[ 'wpm_test_users' ][ 9 ] = (object)[
			'ID'    => 9,
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
			9 => [
				[
					'uuid'      => self::OTHER_UUID,
					'name'      => 'Other',
					'app_id'    => '',
					'created'   => 0,
					'last_used' => 0,
				],
			],
		];
	}

	private function makeFixtureUserAdministratorCapable( int $userId ) :void {
		$GLOBALS[ 'wpm_test_roles' ]->roles[ 'wpm_admin' ] = [
			'read'           => true,
			'manage_options' => true,
		];
		$GLOBALS[ 'wpm_test_users' ][ $userId ]->roles = [ 'wpm_admin' ];
	}

	private function actAsUser( int $userId ) :void {
		$GLOBALS[ 'wpm_test_current_user_id' ] = $userId;
		$GLOBALS[ 'wpm_test_current_user_caps' ] = [ 'read' => true ];
	}

	private function setApplicationPasswordsAvailableForUser( int $userId, bool $available ) :void {
		$GLOBALS[ 'wpm_test_application_passwords_available_for_users' ][ $userId ] = $available;
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
		bool $withNonce,
		?bool $adminLocked = null
	) :void {
		$_SERVER[ 'REQUEST_METHOD' ] = 'POST';
		$_POST = [
			AdminScopeFormSecurity::ACTION_FIELD => $action,
			'user_id'           => (string)$userId,
			'app_password_uuid' => $uuid,
			'allowed_caps'      => $allowedCaps,
			'allowed_meta_caps' => $allowedMetaCaps,
		];
		if ( $adminLocked !== null ) {
			$_POST[ 'admin_locked' ] = $adminLocked ? '1' : '';
		}
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
		$message = $params[ AdminPage::MESSAGE_QUERY_KEY ] ?? '';
		return is_scalar( $message ) ? (string)$message : '';
	}

	/**
	 * @param list<array{name:string,type:string,source:string,area:string,action:string,known:bool}> $items
	 * @return array<string,array{name:string,type:string,source:string,area:string,action:string,known:bool}>
	 */
	private function capabilityItemsByName( array $items ) :array {
		$indexed = [];
		foreach ( $items as $item ) {
			$indexed[ $item[ 'name' ] ] = $item;
		}

		return $indexed;
	}

	/**
	 * @param array{name:string,type:string,source:string,area:string,action:string,known:bool} $item
	 * @return array{source:string,area:string,action:string,type:string,known:bool}
	 */
	private function capabilityItemSummary( array $item ) :array {
		return [
			'source' => $item[ 'source' ],
			'area'   => $item[ 'area' ],
			'action' => $item[ 'action' ],
			'type'   => $item[ 'type' ],
			'known'  => $item[ 'known' ],
		];
	}

	private function actionLinkHref( string $html ) :string {
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );
		return $this->firstElement( $xpath, '//a' )->getAttribute( 'href' );
	}

	private function queryParamFromHref( string $href, string $key ) :string {
		$query = parse_url( $href, PHP_URL_QUERY );
		$this->assertIsString( $query );
		parse_str( $query, $params );
		$value = $params[ $key ] ?? '';
		return is_scalar( $value ) ? (string)$value : '';
	}

	private function captureOutput( callable $callback ) :string {
		ob_start();
		try {
			$callback();
			return (string)ob_get_clean();
		}
		catch ( Throwable $throwable ) {
			ob_end_clean();
			throw $throwable;
		}
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
	 * @return array<string,string>
	 */
	private function capabilityInfoAttributes( string $html, string $capability ) :array {
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );
		$capabilityLiteral = json_encode( $capability, JSON_THROW_ON_ERROR );
		$nodes = $xpath->query( '//*[@data-wpm-capability-item and @data-wpm-capability-name = '.$capabilityLiteral.']//button[contains(concat(" ", normalize-space(@class), " "), " mandate-capability-info ")]' );
		if ( !$nodes instanceof DOMNodeList || $nodes->length < 1 ) {
			throw new RuntimeException( 'Expected rendered capability info button for '.$capability.'.' );
		}

		$node = $nodes->item( 0 );
		if ( !$node instanceof DOMElement ) {
			throw new RuntimeException( 'Expected capability info node to be an element.' );
		}

		$attributes = [];
		foreach ( $node->attributes ?? [] as $attribute ) {
			$attributes[ $attribute->name ] = $attribute->value;
		}

		return $attributes;
	}

	private function capabilityInfoCount( string $html, string $capability ) :int {
		$xpath = new DOMXPath( $this->documentFromHtml( $html ) );
		$capabilityLiteral = json_encode( $capability, JSON_THROW_ON_ERROR );
		return $this->nodeCount(
			$xpath,
			'//*[@data-wpm-capability-item and @data-wpm-capability-name = '.$capabilityLiteral.']//button[contains(concat(" ", normalize-space(@class), " "), " mandate-capability-info ")]'
		);
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function capabilityItemFromViewData( array $data, string $capability ) :array {
		foreach ( $data[ 'vars' ][ 'scope_form' ][ 'source_panels' ] as $panel ) {
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
