<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\MetaCaps\MetaCapabilityRegistry;

final class ScoperTest extends Aps_Test_Case {

	private const UUID = '11111111-1111-4111-8111-111111111111';

	public function testScopeNormalizationStoresBooleanMaps() :void {
		$record = ( new ScopeRepository() )->normalizeRecord(
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
		$this->assertSame( 10, $record[ 'updated_at' ] );
		$this->assertSame( 3, $record[ 'updated_by' ] );
	}

	public function testCapabilityCandidatesComeFromAssignedRolesOnly() :void {
		$GLOBALS[ 'aps_test_roles' ] = new Aps_Test_Roles(
			[
				'aps_editor' => [
					'read'         => true,
					'edit_posts'   => true,
					'delete_posts' => false,
				],
				'aps_admin'  => [
					'manage_options' => true,
				],
			]
		);
		$GLOBALS[ 'aps_test_users' ][ 5 ] = (object)[
			'ID'    => 5,
			'roles' => [ 'aps_editor' ],
			'caps'  => [ 'manage_options' => true ],
		];

		$candidates = ( new CapabilityCandidateProvider() )->forUser( 5 );

		$this->assertSame( [ 'edit_posts' => true, 'read' => true ], $candidates );
		$this->assertArrayNotHasKey( 'manage_options', $candidates );
		$this->assertArrayNotHasKey( 'delete_posts', $candidates );
	}

	public function testNormalRequestWithoutApplicationPasswordContextIsUnchanged() :void {
		$repository = new ScopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], 1 );
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
			new ScopeRepository(),
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
		$GLOBALS[ 'aps_test_rest_uuid' ] = self::UUID;
		$GLOBALS[ 'aps_test_current_user_id' ] = 5;

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

	public function testDeletedApplicationPasswordPrunesScopeRecord() :void {
		$repository = new ScopeRepository();
		$repository->save( self::UUID, 5, [ 'read' => true ], [], 1 );
		$this->assertArrayHasKey( self::UUID, $repository->all() );

		$repository->deleteForApplicationPassword( 5, [ 'uuid' => self::UUID ] );

		$this->assertSame( [], $repository->all() );
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
		$GLOBALS[ 'aps_test_roles' ] = new Aps_Test_Roles(
			[
				'aps_editor' => [
					'read'         => true,
					'edit_posts'   => true,
					'upload_files' => true,
					'delete_posts' => true,
				],
			]
		);
		$GLOBALS[ 'aps_test_users' ][ $userId ] = (object)[
			'ID'    => $userId,
			'roles' => [ 'aps_editor' ],
		];

		$repository = new ScopeRepository();
		$repository->save( $uuid, $userId, $allowedCaps, $allowedMetaCaps, 1 );

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
}
