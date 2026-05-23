<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository;

abstract class MandateWordPressTestCase extends WP_UnitTestCase {

	protected function checkRequirements() {
	}

	public function expectDeprecated() {
		// WordPress' WP_UnitTestCase still reads deprecated-call annotations through
		// a PHPUnit utility method removed before PHPUnit 11. These tests do not use
		// those annotations, so this keeps the WP test lifecycle compatible.
	}
}

final class MandateIntegrationTest extends MandateWordPressTestCase {

	private const UUID = '11111111-1111-4111-8111-111111111111';
	private const OTHER_UUID = '22222222-2222-4222-8222-222222222222';
	private const ROLE = 'mandate_integration_limited';

	public function set_up() :void {
		parent::set_up();

		remove_role( self::ROLE );
		add_role(
			self::ROLE,
			'Mandate Integration Limited',
			[
				'read'         => true,
				'edit_posts'   => true,
				'upload_files' => true,
			]
		);

		( new PluginOptionsRepository() )->replaceScopes( [] );
		$this->resetCapturedApplicationPasswordContext();
		wp_set_current_user( 0 );
	}

	public function tear_down() :void {
		( new PluginOptionsRepository() )->replaceScopes( [] );
		$this->resetCapturedApplicationPasswordContext();
		wp_set_current_user( 0 );
		remove_role( self::ROLE );

		parent::tear_down();
	}

	public function test_plugin_boot_registers_expected_hooks() :void {
		$this->assertHookCallback( 'application_password_did_authenticate', CurrentApplicationPasswordContext::class, 'captureAuthenticatedPassword' );
		$this->assertHookCallback( 'user_has_cap', CapabilityScopeEnforcer::class, 'filterUserCapabilities' );
		$this->assertHookCallback( 'map_meta_cap', CapabilityScopeEnforcer::class, 'filterMetaCaps' );
		$this->assertHookCallback( 'wp_delete_application_password', ScopeRepository::class, 'deleteForApplicationPassword' );
	}

	public function test_scoped_application_password_removes_disallowed_caps() :void {
		$userId = $this->createUser();
		$item = $this->createApplicationPassword( $userId );
		$repository = new ScopeRepository( new PluginOptionsRepository() );
		$repository->save( $item[ 'uuid' ], $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );
		do_action( 'application_password_did_authenticate', get_user_by( 'id', $userId ), $item );

		$this->assertTrue( current_user_can( 'read' ) );
		$this->assertFalse( current_user_can( 'edit_posts' ) );
		$this->assertFalse( current_user_can( 'upload_files' ) );
	}

	public function test_normal_request_keeps_role_caps_unchanged() :void {
		$userId = $this->createUser();
		$repository = new ScopeRepository( new PluginOptionsRepository() );
		$repository->save( self::UUID, $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );

		$this->assertTrue( current_user_can( 'read' ) );
		$this->assertTrue( current_user_can( 'edit_posts' ) );
		$this->assertTrue( current_user_can( 'upload_files' ) );
	}

	public function test_scoped_application_password_denies_unallowlisted_mapped_meta_cap() :void {
		$userId = $this->createUser();
		$postId = $this->createPost( $userId );
		$item = $this->createApplicationPassword( $userId );
		$repository = new ScopeRepository( new PluginOptionsRepository() );
		$repository->save( $item[ 'uuid' ], $userId, [ 'read' => true, 'edit_posts' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );
		do_action( 'application_password_did_authenticate', get_user_by( 'id', $userId ), $item );

		$this->assertFalse( current_user_can( 'edit_post', $postId ) );
	}

	public function test_normal_request_keeps_mapped_meta_cap_behavior_unchanged() :void {
		$userId = $this->createUser();
		$postId = $this->createPost( $userId );
		$repository = new ScopeRepository( new PluginOptionsRepository() );
		$repository->save( self::UUID, $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );

		$this->assertTrue( current_user_can( 'edit_post', $postId ) );
	}

	public function test_deleted_application_password_prunes_only_matching_scope() :void {
		$userId = $this->createUser();
		$otherUserId = $this->createUser();
		$repository = new ScopeRepository( new PluginOptionsRepository() );
		$repository->save( self::UUID, $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );
		$repository->save( self::OTHER_UUID, $otherUserId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		do_action( 'wp_delete_application_password', $userId, [ 'uuid' => self::UUID ] );

		$this->assertNull( $repository->find( self::UUID ) );
		$remaining = $repository->find( self::OTHER_UUID );
		$this->assertNotNull( $remaining );
		$this->assertSame( $otherUserId, $remaining[ 'user_id' ] );
	}

	public function test_real_application_password_deletion_prunes_only_matching_scope() :void {
		$userId = $this->createUser();
		$otherUserId = $this->createUser();
		$item = $this->createApplicationPassword( $userId );
		$repository = new ScopeRepository( new PluginOptionsRepository() );
		$repository->save( $item[ 'uuid' ], $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );
		$repository->save( self::OTHER_UUID, $otherUserId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		$this->assertTrue( WP_Application_Passwords::delete_application_password( $userId, $item[ 'uuid' ] ) );

		$this->assertNull( $repository->find( $item[ 'uuid' ] ) );
		$remaining = $repository->find( self::OTHER_UUID );
		$this->assertNotNull( $remaining );
		$this->assertSame( $otherUserId, $remaining[ 'user_id' ] );
	}

	private function createUser() :int {
		return (int)self::factory()->user->create( [ 'role' => self::ROLE ] );
	}

	private function createPost( int $authorId ) :int {
		return (int)self::factory()->post->create(
			[
				'post_author' => $authorId,
				'post_status' => 'draft',
			]
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function createApplicationPassword( int $userId ) :array {
		$result = WP_Application_Passwords::create_new_application_password(
			$userId,
			[
				'name'   => 'Mandate Integration',
				'app_id' => wp_generate_uuid4(),
			]
		);
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 1, $result );
		$this->assertIsArray( $result[ 1 ] );

		return $result[ 1 ];
	}

	private function resetCapturedApplicationPasswordContext() :void {
		$context = $this->hookCallbackObject(
			'application_password_did_authenticate',
			CurrentApplicationPasswordContext::class,
			'captureAuthenticatedPassword'
		);
		if ( $context instanceof CurrentApplicationPasswordContext ) {
			$context->setContext( 0, '' );
		}
	}

	private function assertHookCallback( string $hook, string $class, string $method ) :void {
		$this->assertInstanceOf(
			$class,
			$this->hookCallbackObject( $hook, $class, $method ),
			$hook.' should include '.$class.'::'.$method.'().'
		);
	}

	private function hookCallbackObject( string $hook, string $class, string $method ) :?object {
		global $wp_filter;

		$wpHook = $wp_filter[ $hook ] ?? null;
		$callbacks = $wpHook instanceof WP_Hook ? $wpHook->callbacks : [];
		foreach ( $callbacks as $priorityCallbacks ) {
			foreach ( $priorityCallbacks as $callback ) {
				$function = $callback[ 'function' ] ?? null;
				if ( is_array( $function )
					&& isset( $function[ 0 ], $function[ 1 ] )
					&& $function[ 0 ] instanceof $class
					&& $function[ 1 ] === $method
				) {
					return $function[ 0 ];
				}
			}
		}

		return null;
	}
}
