<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPageViewDataBuilder;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminScopeFormSecurity;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminTemplateRenderer;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminTrustedHtmlSanitizer;
use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminUserRoleProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\CurrentApplicationPasswordContext;
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

final class ExpirationTest extends Wpm_Test_Case {

	private const UUID = '11111111-1111-4111-8111-111111111111';
	private const OTHER_UUID = '22222222-2222-4222-8222-222222222222';

	public function testV1ScopeRecordsNormalizeAsRestrictedWithoutReadMutation() :void {
		$stored = [
			'metadata' => [
				'schema_version' => 1,
				'plugin_version' => '0.1.0',
				'created_at'     => 100,
				'updated_at'     => 200,
			],
			'scopes'   => [
				self::UUID => [
					'user_id'           => 5,
					'allowed_caps'      => [ 'read' => true ],
					'allowed_meta_caps' => [ 'edit_post' => true ],
					'roles_at_update'   => [ 'wpm_editor' ],
					'updated_at'        => 200,
					'updated_by'        => 1,
				],
			],
		];
		$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] = $stored;

		$record = $this->scopeRepository()->find( self::UUID );

		$this->assertNotNull( $record );
		$this->assertTrue( $record[ 'capabilities_restricted' ] );
		$this->assertSame( [ 'read' => true ], $record[ 'allowed_caps' ] );
		$this->assertSame( [ 'edit_post' => true ], $record[ 'allowed_meta_caps' ] );
		$this->assertNull( $record[ 'expires_on' ] );
		$this->assertSame( $stored, $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] );
	}

	public function testV2ExpirationOnlyRecordsNormalizeToUnrestrictedCapabilityShape() :void {
		$repository = $this->scopeRepository();

		$this->assertTrue( $repository->save( self::UUID, 5, [ 'read' => true ], [ 'edit_post' => true ], [ 'wpm_editor' ], 1, '2026-05-24', false ) );
		$record = $repository->find( self::UUID );

		$this->assertNotNull( $record );
		$this->assertFalse( $record[ 'capabilities_restricted' ] );
		$this->assertSame( [], $record[ 'allowed_caps' ] );
		$this->assertSame( [], $record[ 'allowed_meta_caps' ] );
		$this->assertSame( '2026-05-24', $record[ 'expires_on' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
		$this->assertSame(
			PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
			$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ][ 'metadata' ][ 'schema_version' ]
		);
	}

	public function testMalformedStoredExpirationDatesNormalizeToNeverExpires() :void {
		$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] = [
			'metadata' => [
				'schema_version' => PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
				'plugin_version' => Plugin::VERSION,
				'created_at'     => 100,
				'updated_at'     => 200,
			],
			'scopes'   => [
				self::UUID => [
					'user_id'                 => 5,
					'capabilities_restricted' => false,
					'allowed_caps'            => [ 'read' => true ],
					'allowed_meta_caps'       => [],
					'expires_on'              => '2026-02-31',
					'roles_at_update'         => [ 'wpm_editor' ],
					'updated_at'              => 200,
					'updated_by'              => 1,
				],
			],
		];

		$record = $this->scopeRepository()->find( self::UUID );

		$this->assertNotNull( $record );
		$this->assertNull( $record[ 'expires_on' ] );
		$this->assertFalse( $record[ 'capabilities_restricted' ] );
		$this->assertSame( [], $record[ 'allowed_caps' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
	}

	public function testMalformedStoredRestrictionFlagDefaultsToRestricted() :void {
		$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] = [
			'metadata' => [
				'schema_version' => PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
				'plugin_version' => Plugin::VERSION,
				'created_at'     => 100,
				'updated_at'     => 200,
			],
			'scopes'   => [
				self::UUID => [
					'user_id'                 => 5,
					'capabilities_restricted' => '0',
					'allowed_caps'            => [ 'read' => true ],
					'allowed_meta_caps'       => [],
					'expires_on'              => null,
					'roles_at_update'         => [ 'wpm_editor' ],
					'updated_at'              => 200,
					'updated_by'              => 1,
				],
			],
		];

		$record = $this->scopeRepository()->find( self::UUID );

		$this->assertNotNull( $record );
		$this->assertTrue( $record[ 'capabilities_restricted' ] );
		$this->assertSame( [ 'read' => true ], $record[ 'allowed_caps' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
	}

	public function testExpirationPolicyUsesSiteLocalDateContract() :void {
		$policy = new ExpirationDatePolicy();

		$this->assertSame( '2026-05-23', $policy->today() );
		$this->assertTrue( $policy->isExpired( '2026-05-22' ) );
		$this->assertFalse( $policy->isExpired( '2026-05-23' ) );
		$this->assertFalse( $policy->isExpired( '2026-05-24' ) );
		$this->assertNull( $policy->normalize( '2026-2-3' ) );
		$this->assertNull( $policy->normalize( '2026-02-31' ) );
	}

	public function testUnexpiredExpirationOnlyPasswordPreservesNormalCapabilities() :void {
		$enforcer = $this->enforcerWithRecord( '2026-05-24', false );
		$allcaps = [
			'read'         => true,
			'edit_posts'   => true,
			'delete_posts' => true,
		];

		$this->assertSame(
			$allcaps,
			$enforcer->filterUserCapabilities( $allcaps, [ 'delete_posts' ], [ 'delete_posts', 5 ], (object)[ 'ID' => 5 ] )
		);
		$this->assertSame(
			[ 'edit_posts' ],
			$enforcer->filterMetaCaps( [ 'edit_posts' ], 'edit_post', 5, [ 123 ] )
		);
	}

	public function testExpiredPasswordRemovesAllPrimitiveAndMetaCapabilities() :void {
		$enforcer = $this->enforcerWithRecord( '2026-05-22', false );

		$filtered = $enforcer->filterUserCapabilities(
			[
				'read'         => true,
				'edit_posts'   => true,
				'delete_posts' => true,
			],
			[ 'read' ],
			[ 'read', 5 ],
			(object)[ 'ID' => 5 ]
		);

		$this->assertFalse( $filtered[ 'read' ] );
		$this->assertFalse( $filtered[ 'edit_posts' ] );
		$this->assertFalse( $filtered[ 'delete_posts' ] );
		$this->assertSame(
			[ 'do_not_allow' ],
			$enforcer->filterMetaCaps( [ 'edit_posts' ], 'edit_post', 5, [ 123 ] )
		);
	}

	public function testAdminPostSavesExpirationOnlyRecordWhenAllCapabilitiesAreSelected() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$this->submitScopePost(
			'save_scope',
			5,
			self::UUID,
			[ 'read', 'edit_posts', 'upload_files' ],
			array_keys( ( new MetaCapabilityRegistry() )->registered() ),
			'2026-05-24',
			true
		);

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $location ) );
		$this->assertNotNull( $record );
		$this->assertFalse( $record[ 'capabilities_restricted' ] );
		$this->assertSame( '2026-05-24', $record[ 'expires_on' ] );
		$this->assertSame( [], $record[ 'allowed_caps' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
	}

	public function testAdminRenderSummarizesRestrictionStates() :void {
		$cases = [
			'unrestricted'    => [
				null,
				'Unrestricted',
				'Never expires',
				'never',
			],
			'capabilities'    => [
				static function ( ScopeRepository $repository ) :void {
					$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1 );
				},
				'Capabilities',
				'Never expires',
				'never',
			],
			'expiration'      => [
				static function ( ScopeRepository $repository ) :void {
					$repository->save( self::UUID, 5, [], [], [ 'wpm_editor' ], 1, '2026-05-24', false );
				},
				'Expiration date',
				'2026-05-24',
				'date',
			],
			'combined'        => [
				static function ( ScopeRepository $repository ) :void {
					$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1, '2026-05-24' );
				},
				'Capabilities / Expiration date',
				'2026-05-24',
				'date',
			],
			'expired'         => [
				static function ( ScopeRepository $repository ) :void {
					$repository->save( self::UUID, 5, [], [], [ 'wpm_editor' ], 1, '2026-05-22', false );
				},
				'Expiration date',
				'2026-05-22 (expired)',
				'expired',
			],
		];

		foreach ( $cases as $case => [ $seedScope, $restrictionSummary, $expirationSummary, $expirationState ] ) {
			wpm_test_reset_state();
			$this->seedAdminFixture();
			$repository = $this->scopeRepository();
			if ( is_callable( $seedScope ) ) {
				$seedScope( $repository );
			}

			$document = $this->renderAdminDocument( $repository );
			$xpath = new DOMXPath( $document );
			$summary = $this->summaryDetailValue( $xpath, 'Restricted Scope' );
			$expiration = $this->summaryDetailValue( $xpath, 'Expiration Date' );
			$expirationButton = $this->firstElement( $xpath, '//*[@data-wpm-expiration-summary]' );

			$this->assertSame( $restrictionSummary, $summary, $case );
			$this->assertSame( $expirationSummary, $expiration, $case );
			$this->assertSame( $expirationState, $expirationButton->getAttribute( 'data-wpm-expiration-state' ), $case );
			$this->assertNull( $this->summaryDetailValueOrNull( $xpath, 'Current Roles' ), $case );
		}
	}

	public function testAdminRenderKeepsLegacyUnknownRoleSnapshotAsNotRecorded() :void {
		$this->seedAdminFixture();
		$optionsRepository = new PluginOptionsRepository();
		$repository = $this->scopeRepository( $optionsRepository );
		$stored = [
			'metadata' => [
				'schema_version' => PluginOptionsRepository::CURRENT_SCHEMA_VERSION,
				'plugin_version' => Plugin::VERSION,
				'created_at'     => 100,
				'updated_at'     => 200,
			],
			'scopes'   => [
				self::UUID => [
					'user_id'                 => 5,
					'capabilities_restricted' => false,
					'allowed_caps'            => [],
					'allowed_meta_caps'       => [],
					'expires_on'              => '2026-05-24',
					'updated_at'              => 200,
					'updated_by'              => 1,
				],
			],
		];
		$GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] = $stored;

		$document = $this->renderAdminDocument( $repository );

		$this->assertSame( 'Not recorded', $this->summaryDetailValue( new DOMXPath( $document ), 'Roles When Saved' ) );
		$this->assertSame( $stored, $GLOBALS[ 'wpm_test_options' ][ PluginOptionsRepository::OPTION_NAME ] );
	}

	public function testAdminPostSavesRestrictedRecordWithExpirationDate() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$this->submitScopePost(
			'save_scope',
			5,
			self::UUID,
			[ 'read', 'upload_files', 'manage_options', 'Bad Cap' ],
			[ 'edit_post', 'wpm_missing_meta' ],
			'2026-05-24',
			true
		);

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );
		$record = $repository->findForUser( 5, self::UUID );

		$this->assertSame( 'saved', $this->redirectMessage( $location ) );
		$this->assertNotNull( $record );
		$this->assertTrue( $record[ 'capabilities_restricted' ] );
		$this->assertSame(
			[ 'read' => true, 'upload_files' => true ],
			$record[ 'allowed_caps' ]
		);
		$this->assertSame( [ 'edit_post' => true ], $record[ 'allowed_meta_caps' ] );
		$this->assertSame( '2026-05-24', $record[ 'expires_on' ] );
		$this->assertSame( [ 'wpm_editor' ], $record[ 'roles_at_update' ] );
	}

	public function testAdminPostAllCapabilitiesWithoutExpirationDeletesExistingExpirationRecord() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1, '2026-05-24' );
		$repository->save( self::OTHER_UUID, 9, [ 'read' => true ], [], [ 'wpm_editor' ], 1, '2026-05-24' );
		$otherBefore = $repository->find( self::OTHER_UUID );
		$this->submitScopePost(
			'save_scope',
			5,
			self::UUID,
			[ 'read', 'edit_posts', 'upload_files' ],
			array_keys( ( new MetaCapabilityRegistry() )->registered() ),
			'',
			true
		);

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 'reset', $this->redirectMessage( $location ) );
		$this->assertNull( $repository->find( self::UUID ) );
		$this->assertSame( $otherBefore, $repository->find( self::OTHER_UUID ) );
	}

	public function testAdminPostRejectsInvalidExpirationDateWithoutMutation() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1, '2026-05-24' );
		$before = $repository->find( self::UUID );
		$this->submitScopePost( 'save_scope', 5, self::UUID, [ 'read' ], [], '2026-02-31', true );

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 'invalid', $this->redirectMessage( $location ) );
		$this->assertSame( $before, $repository->find( self::UUID ) );
	}

	public function testAdminPostClearRemovesCapabilitiesAndExpiration() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], [ 'wpm_editor' ], 1, '2026-05-24' );
		$this->submitScopePost( 'clear_scope', 5, self::UUID, [], [], '', true );

		$location = $this->handlePostExpectRedirect( $this->adminPage( $repository ) );

		$this->assertSame( 'reset', $this->redirectMessage( $location ) );
		$this->assertNull( $repository->find( self::UUID ) );
	}

	public function testAdminRenderExposesExpirationSummaryAndInputContract() :void {
		$this->seedAdminFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [], [], [], 1, '2026-05-24', false );

		$document = $this->renderAdminDocument( $repository );
		$xpath = new DOMXPath( $document );
		$inputs = $xpath->query( '//*[@data-wpm-expiration-input]' );
		$summaryInputs = $xpath->query( '//*[@id="mandate-rules-summary"]//*[@data-wpm-expiration-input]' );
		$scopeFormInputs = $xpath->query( '//*[@id="mandate-scope-form"]//*[@data-wpm-expiration-input]' );
		$summaries = $xpath->query( '//*[@data-wpm-expiration-summary]' );
		$roleCaps = $xpath->query( '//input[@name="allowed_caps[]" and @value="upload_files"]' );
		$this->assertInstanceOf( DOMNodeList::class, $inputs );
		$this->assertInstanceOf( DOMNodeList::class, $summaryInputs );
		$this->assertInstanceOf( DOMNodeList::class, $scopeFormInputs );
		$this->assertInstanceOf( DOMNodeList::class, $summaries );
		$this->assertInstanceOf( DOMNodeList::class, $roleCaps );
		$this->assertSame( 1, $inputs->length );
		$this->assertSame( 1, $summaryInputs->length );
		$this->assertSame( 0, $scopeFormInputs->length );
		$input = $inputs->item( 0 );
		$summary = $summaries->item( 0 );
		$roleCap = $roleCaps->item( 0 );

		$this->assertInstanceOf( DOMElement::class, $input );
		$this->assertSame( 'date', $input->getAttribute( 'type' ) );
		$this->assertSame( 'expiration_date', $input->getAttribute( 'name' ) );
		$this->assertSame( 'mandate-scope-form', $input->getAttribute( 'form' ) );
		$this->assertSame( 'Expiration Date', $input->getAttribute( 'aria-label' ) );
		$this->assertSame( '2026-05-24', $input->getAttribute( 'value' ) );
		$this->assertFalse( $input->hasAttribute( 'hidden' ) );
		$this->assertInstanceOf( DOMElement::class, $summary );
		$this->assertSame( 'date', $summary->getAttribute( 'data-wpm-expiration-state' ) );
		$this->assertTrue( $summary->hasAttribute( 'hidden' ) );
		$this->assertSame( $input->getAttribute( 'id' ), $summary->getAttribute( 'aria-controls' ) );
		$this->assertInstanceOf( DOMElement::class, $roleCap );
		$this->assertSame( 'checked', $roleCap->getAttribute( 'checked' ) );
	}

	public function testCronSchedulingDeactivationAndReaperDeleteExpiredPasswordsOnly() :void {
		$this->seedPasswordFixture();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, 5, [], [], [], 1, '2026-05-22', false );
		$repository->save( self::OTHER_UUID, 5, [], [], [], 1, '2026-05-24', false );
		add_action( 'wp_delete_application_password', [ $repository, 'deleteForApplicationPassword' ], 10, 2 );

		$reaper = new ApplicationPasswordExpirationReaper( $repository, new ExpirationDatePolicy() );
		$reaper->registerHooks();

		$this->assertNotFalse( wp_next_scheduled( ApplicationPasswordExpirationReaper::HOOK ) );
		do_action( ApplicationPasswordExpirationReaper::HOOK );

		$this->assertNull( $repository->find( self::UUID ) );
		$this->assertNotNull( $repository->find( self::OTHER_UUID ) );
		$this->assertSame( [ self::OTHER_UUID ], array_column( WP_Application_Passwords::$passwordsByUser[ 5 ], 'uuid' ) );

		ApplicationPasswordExpirationReaper::clearScheduledHook();
		$this->assertFalse( wp_next_scheduled( ApplicationPasswordExpirationReaper::HOOK ) );
	}

	public function testPluginBootRegistersExpirationHooksAndDeactivationCleanup() :void {
		$pluginFile = $this->pluginFile();
		Plugin::boot( $pluginFile );

		$this->assertNotFalse( wp_next_scheduled( ApplicationPasswordExpirationReaper::HOOK ) );
		$this->assertArrayHasKey( ApplicationPasswordExpirationReaper::HOOK, $GLOBALS[ 'wpm_test_actions' ] );
		$this->assertArrayHasKey( $pluginFile, $GLOBALS[ 'wpm_test_deactivation_hooks' ] );

		$GLOBALS[ 'wpm_test_deactivation_hooks' ][ $pluginFile ]();

		$this->assertFalse( wp_next_scheduled( ApplicationPasswordExpirationReaper::HOOK ) );
	}

	private function seedPasswordFixture() :void {
		WP_Application_Passwords::$passwordsByUser = [
			5 => [
				[
					'uuid'      => self::UUID,
					'name'      => 'Expired',
					'app_id'    => '',
					'created'   => 0,
					'last_used' => 0,
				],
				[
					'uuid'      => self::OTHER_UUID,
					'name'      => 'Future',
					'app_id'    => '',
					'created'   => 0,
					'last_used' => 0,
				],
			],
		];
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

	private function enforcerWithRecord( string $expiresOn, bool $capabilitiesRestricted ) :CapabilityScopeEnforcer {
		$GLOBALS[ 'wpm_test_roles' ] = new Wpm_Test_Roles(
			[
				'wpm_editor' => [
					'read'       => true,
					'edit_posts' => true,
				],
			]
		);
		$GLOBALS[ 'wpm_test_users' ][ 5 ] = (object)[
			'ID'    => 5,
			'roles' => [ 'wpm_editor' ],
		];

		$repository = $this->scopeRepository();
		$repository->save(
			self::UUID,
			5,
			$capabilitiesRestricted ? [ 'read' => true ] : [],
			[],
			[ 'wpm_editor' ],
			1,
			$expiresOn,
			$capabilitiesRestricted
		);
		$context = new CurrentApplicationPasswordContext();
		$context->setContext( 5, self::UUID );

		return new CapabilityScopeEnforcer(
			$repository,
			new CapabilityCandidateProvider(),
			$context,
			new MetaCapabilityRegistry(),
			new ExpirationDatePolicy()
		);
	}

	private function adminPage( ScopeRepository $repository ) :AdminPage {
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

	private function pluginFile() :string {
		return dirname( __DIR__, 2 ).'/'.PluginIdentity::MAIN_FILE;
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
		string $expirationDate,
		bool $withNonce
	) :void {
		$_SERVER[ 'REQUEST_METHOD' ] = 'POST';
		$_POST = [
			'mandate_action'    => $action,
			'user_id'           => (string)$userId,
			'app_password_uuid' => $uuid,
			'allowed_caps'      => $allowedCaps,
			'allowed_meta_caps' => $allowedMetaCaps,
			'expiration_date'   => $expirationDate,
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

	private function renderAdminDocument( ScopeRepository $repository ) :DOMDocument {
		$_GET = [
			'page'              => Plugin::MENU_SLUG,
			'user_id'           => '5',
			'app_password_uuid' => self::UUID,
		];

		ob_start();
		try {
			$this->adminPage( $repository )->render();
			$html = (string)ob_get_clean();
		}
		catch ( Throwable $throwable ) {
			ob_end_clean();
			throw $throwable;
		}

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

	private function summaryDetailValue( DOMXPath $xpath, string $label ) :string {
		$value = $this->summaryDetailValueOrNull( $xpath, $label );
		if ( $value === null ) {
			throw new RuntimeException( 'Expected Mandate rules summary detail for '.$label.'.' );
		}

		return $value;
	}

	private function summaryDetailValueOrNull( DOMXPath $xpath, string $label ) :?string {
		$labelLiteral = json_encode( $label, JSON_THROW_ON_ERROR );
		$nodes = $xpath->query( '//*[@id="mandate-rules-summary"]//dt[normalize-space(.) = '.$labelLiteral.']/following-sibling::dd[1]' );
		if ( !$nodes instanceof DOMNodeList || $nodes->length < 1 ) {
			return null;
		}

		$node = $nodes->item( 0 );
		return $node === null ? null : trim( $node->textContent );
	}

	private function firstElement( DOMXPath $xpath, string $query ) :DOMElement {
		$nodes = $xpath->query( $query );
		if ( !$nodes instanceof DOMNodeList || $nodes->length < 1 ) {
			throw new RuntimeException( 'Expected element for query '.$query.'.' );
		}

		$node = $nodes->item( 0 );
		if ( !$node instanceof DOMElement ) {
			throw new RuntimeException( 'Expected query '.$query.' to return an element.' );
		}

		return $node;
	}

	private function scopeRepository( ?PluginOptionsRepository $optionsRepository = null ) :ScopeRepository {
		return new ScopeRepository( $optionsRepository ?? new PluginOptionsRepository(), new ExpirationDatePolicy() );
	}
}
