<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ApplicationPasswordExpirationReaper;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\PluginServices;

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
		$this->assertHookCallable( 'application_password_did_authenticate' );
		$this->assertHookCallable( 'user_has_cap' );
		$this->assertHookCallable( 'map_meta_cap' );
		$this->assertHookCallable( 'wp_delete_application_password' );
		$this->assertHookCallable( ApplicationPasswordExpirationReaper::HOOK );
		$this->assertNotFalse( wp_next_scheduled( ApplicationPasswordExpirationReaper::HOOK ) );
	}

	public function test_scoped_application_password_removes_disallowed_caps() :void {
		$userId = $this->createUser();
		$item = $this->createApplicationPassword( $userId );
		$repository = $this->scopeRepository();
		$repository->save( $item[ 'uuid' ], $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );
		do_action( 'application_password_did_authenticate', get_user_by( 'id', $userId ), $item );

		$this->assertTrue( current_user_can( 'read' ) );
		$this->assertFalse( current_user_can( 'edit_posts' ) );
		$this->assertFalse( current_user_can( 'upload_files' ) );
	}

	public function test_normal_request_keeps_role_caps_unchanged() :void {
		$userId = $this->createUser();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );

		$this->assertTrue( current_user_can( 'read' ) );
		$this->assertTrue( current_user_can( 'edit_posts' ) );
		$this->assertTrue( current_user_can( 'upload_files' ) );
	}

	public function test_expired_application_password_removes_all_caps() :void {
		$userId = $this->createUser();
		$item = $this->createApplicationPassword( $userId );
		$repository = $this->scopeRepository();
		$repository->save( $item[ 'uuid' ], $userId, [], [], [], 1, '2000-01-01', false );

		wp_set_current_user( $userId );
		do_action( 'application_password_did_authenticate', get_user_by( 'id', $userId ), $item );

		$this->assertFalse( current_user_can( 'read' ) );
		$this->assertFalse( current_user_can( 'edit_posts' ) );
		$this->assertFalse( current_user_can( 'upload_files' ) );
	}

	public function test_normal_request_keeps_role_caps_when_stored_expiration_is_expired() :void {
		$userId = $this->createUser();
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, $userId, [], [], [], 1, '2000-01-01', false );

		wp_set_current_user( $userId );

		$this->assertTrue( current_user_can( 'read' ) );
		$this->assertTrue( current_user_can( 'edit_posts' ) );
		$this->assertTrue( current_user_can( 'upload_files' ) );
	}

	public function test_scoped_application_password_denies_unallowlisted_mapped_meta_cap() :void {
		$userId = $this->createUser();
		$postId = $this->createPost( $userId );
		$item = $this->createApplicationPassword( $userId );
		$repository = $this->scopeRepository();
		$repository->save( $item[ 'uuid' ], $userId, [ 'read' => true, 'edit_posts' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );
		do_action( 'application_password_did_authenticate', get_user_by( 'id', $userId ), $item );

		$this->assertFalse( current_user_can( 'edit_post', $postId ) );
	}

	public function test_normal_request_keeps_mapped_meta_cap_behavior_unchanged() :void {
		$userId = $this->createUser();
		$postId = $this->createPost( $userId );
		$repository = $this->scopeRepository();
		$repository->save( self::UUID, $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		wp_set_current_user( $userId );

		$this->assertTrue( current_user_can( 'edit_post', $postId ) );
	}

	public function test_deleted_application_password_prunes_only_matching_scope() :void {
		$userId = $this->createUser();
		$otherUserId = $this->createUser();
		$repository = $this->scopeRepository();
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
		$repository = $this->scopeRepository();
		$repository->save( $item[ 'uuid' ], $userId, [ 'read' => true ], [], [ self::ROLE ], 1 );
		$repository->save( self::OTHER_UUID, $otherUserId, [ 'read' => true ], [], [ self::ROLE ], 1 );

		$this->assertTrue( WP_Application_Passwords::delete_application_password( $userId, $item[ 'uuid' ] ) );

		$this->assertNull( $repository->find( $item[ 'uuid' ] ) );
		$remaining = $repository->find( self::OTHER_UUID );
		$this->assertNotNull( $remaining );
		$this->assertSame( $otherUserId, $remaining[ 'user_id' ] );
	}

	public function test_expiration_reaper_revokes_real_expired_application_password_and_prunes_scope() :void {
		$userId = $this->createUser();
		$item = $this->createApplicationPassword( $userId );
		$repository = $this->scopeRepository();
		$repository->save( $item[ 'uuid' ], $userId, [], [], [], 1, '2000-01-01', false );

		do_action( ApplicationPasswordExpirationReaper::HOOK );

		$this->assertNull( $repository->find( $item[ 'uuid' ] ) );
		$this->assertFalse( $this->applicationPasswordExists( $userId, $item[ 'uuid' ] ) );
	}

	private function createUser() :int {
		return (int)self::factory()->user->create( [ 'role' => self::ROLE ] );
	}

	private function scopeRepository() :ScopeRepository {
		return new ScopeRepository( new PluginOptionsRepository(), new ExpirationDatePolicy() );
	}

	private function createPost( int $authorId ) :int {
		return (int)self::factory()->post->create(
			[
				'post_author' => $authorId,
				'post_status' => 'draft',
			]
		);
	}

	private function applicationPasswordExists( int $userId, string $uuid ) :bool {
		foreach ( WP_Application_Passwords::get_user_application_passwords( $userId ) as $password ) {
			if ( is_array( $password ) && ( $password[ 'uuid' ] ?? null ) === $uuid ) {
				return true;
			}
		}

		return false;
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
		$services = $this->hookCallbackServices( 'application_password_did_authenticate' );
		if ( $services instanceof PluginServices ) {
			$services->currentApplicationPasswordContext()->setContext( 0, '' );
		}
	}

	private function assertHookCallable( string $hook ) :void {
		$this->assertTrue( $this->hasHookCallable( $hook ), $hook.' should include a callable callback.' );
	}

	private function hasHookCallable( string $hook ) :bool {
		global $wp_filter;

		$wpHook = $wp_filter[ $hook ] ?? null;
		$callbacks = $wpHook instanceof WP_Hook ? $wpHook->callbacks : [];
		foreach ( $callbacks as $priorityCallbacks ) {
			foreach ( $priorityCallbacks as $callback ) {
				$function = $callback[ 'function' ] ?? null;
				if ( is_callable( $function ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function hookCallbackServices( string $hook ) :?PluginServices {
		global $wp_filter;

		$wpHook = $wp_filter[ $hook ] ?? null;
		$callbacks = $wpHook instanceof WP_Hook ? $wpHook->callbacks : [];
		foreach ( $callbacks as $priorityCallbacks ) {
			foreach ( $priorityCallbacks as $callback ) {
				$function = $callback[ 'function' ] ?? null;
				if ( !$function instanceof Closure ) {
					continue;
				}

				$services = ( new ReflectionFunction( $function ) )->getStaticVariables()[ 'services' ] ?? null;
				if ( $services instanceof PluginServices ) {
					return $services;
				}
			}
		}

		return null;
	}
}
