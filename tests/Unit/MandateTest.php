<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Plugin;

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

	public function testApplicationPasswordRepositoryOwnsNormalizedPasswordRecordContract() :void {
		WP_Application_Passwords::$passwordsByUser = [
			5 => [
				[
					'uuid'      => '11111111-1111-4111-8111-111111111111',
					'name'      => 'Client App',
					'app_id'    => 123,
					'created'   => '10',
					'last_used' => null,
				],
				[
					'uuid' => 'not-a-uuid',
					'name' => 'Broken',
				],
			],
		];

		$this->assertSame(
			[
				[
					'uuid'      => '11111111-1111-4111-8111-111111111111',
					'name'      => 'Client App',
					'app_id'    => '123',
					'created'   => 10,
					'last_used' => 0,
				],
			],
			( new ApplicationPasswordRepository() )->forUser( 5 )
		);
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
			new MetaCapabilityRegistry()
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
			new MetaCapabilityRegistry()
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

		$this->handlePostExpectRedirect( $this->adminPage() );

		$this->assertSame( [], $this->scopeRepository()->all() );
		$this->assertTrue( str_contains( (string)$GLOBALS[ 'wpm_test_last_redirect' ], 'mandate_message=invalid' ) );
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

		$this->assertSame(
			[ 'read' => true, 'upload_files' => true ],
			$record[ 'allowed_caps' ]
		);
		$this->assertSame( [ 'edit_post' => true ], $record[ 'allowed_meta_caps' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
		$this->assertSame( false, $GLOBALS[ 'wpm_test_autoload' ][ PluginOptionsRepository::OPTION_NAME ] );
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

	public function testAdminPostClearsOnlyOwnedScope() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [], 1 );
		$repository->save( self::OTHER_UUID, 9, [ 'read' => true ], [], [], 1 );
		$this->submitScopePost( 'clear_scope', 5, self::UUID, [], [], true );

		$this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( null, $repository->findForUser( 5, self::UUID ) );
		$this->assertArrayHasKey( self::OTHER_UUID, $repository->all() );
	}

	public function testAdminPostClearRefusesScopeOwnedByDifferentUser() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 9, [ 'read' => true ], [], [], 1 );
		$this->submitScopePost( 'clear_scope', 5, self::UUID, [], [], true );

		$this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 9, $repository->find( self::UUID )[ 'user_id' ] );
		$this->assertTrue( str_contains( (string)$GLOBALS[ 'wpm_test_last_redirect' ], 'mandate_message=invalid' ) );
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

	private function adminPage( ?ScopeRepository $repository = null ) :AdminPage {
		return new AdminPage(
			$repository ?? $this->scopeRepository(),
			new ApplicationPasswordRepository(),
			new CapabilityCandidateProvider(),
			new MetaCapabilityRegistry(),
			new CapabilityGroupProvider(),
			dirname( __DIR__, 2 ).'/plugin.php'
		);
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
			$nonceName = $this->adminPagePrivateString( 'nonceName', [ $action ] );
			$_POST[ $nonceName ] = wpm_test_set_valid_nonce(
				$nonceName,
				$this->adminPagePrivateString( 'nonceAction', [ $action, $userId, $uuid ] )
			);
		}
	}

	private function handlePostExpectRedirect( AdminPage $adminPage ) :void {
		try {
			$adminPage->handlePost();
		}
		catch ( Wpm_Test_Redirect_Exception ) {
			return;
		}

		throw new RuntimeException( 'Expected admin POST to redirect.' );
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

	/**
	 * @param array<int,mixed> $args
	 */
	private function adminPagePrivateString( string $method, array $args ) :string {
		$reflection = new ReflectionMethod( AdminPage::class, $method );
		$reflection->setAccessible( true );
		$result = $reflection->invokeArgs( $this->adminPage(), $args );
		if ( !is_string( $result ) ) {
			throw new RuntimeException( 'Expected AdminPage::'.$method.'() to return a string.' );
		}

		return $result;
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
			new MetaCapabilityRegistry()
		);
	}

	private function extractContext( CapabilityScopeEnforcer $enforcer ) :CurrentApplicationPasswordContext {
		$reflection = new ReflectionClass( $enforcer );
		$property = $reflection->getProperty( 'context' );
		$property->setAccessible( true );
		return $property->getValue( $enforcer );
	}

	private function scopeRepository( ?PluginOptionsRepository $optionsRepository = null ) :ScopeRepository {
		return new ScopeRepository( $optionsRepository ?? new PluginOptionsRepository() );
	}
}
