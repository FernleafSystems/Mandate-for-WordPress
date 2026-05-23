<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;

final class ApplicationPasswordRepositoryTest extends Wpm_Test_Case {

	private const UUID = '11111111-1111-4111-8111-111111111111';

	public function testForUserIgnoresInvalidUserIdsAndNonArrayPasswordSources() :void {
		WP_Application_Passwords::$passwordsByUser = [
			5 => 'not-a-list',
		];

		$repository = new ApplicationPasswordRepository();

		$this->assertSame( [], $repository->forUser( 0 ) );
		$this->assertSame( [], $repository->forUser( 5 ) );
	}

	public function testForUserNormalizesValidRowsAndSkipsMalformedRows() :void {
		WP_Application_Passwords::$passwordsByUser = [
			5 => [
				'not-a-row',
				[
					'uuid'      => strtoupper( self::UUID ),
					'name'      => '',
					'app_id'    => 123,
					'created'   => -10,
					'last_used' => '20',
				],
				[
					'uuid' => 'not-a-uuid',
					'name' => 'Ignored',
				],
			],
		];

		$this->assertSame(
			[
				[
					'uuid'      => self::UUID,
					'name'      => self::UUID,
					'app_id'    => '123',
					'created'   => 0,
					'last_used' => 20,
				],
			],
			( new ApplicationPasswordRepository() )->forUser( 5 )
		);
	}

	public function testFindAndOwnershipNormalizeUuidBeforeLookup() :void {
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

		$repository = new ApplicationPasswordRepository();
		$record = $repository->findForUser( 5, strtoupper( self::UUID ) );

		$this->assertNotNull( $record );
		$this->assertSame( self::UUID, $record[ 'uuid' ] );
		$this->assertTrue( $repository->userOwnsPassword( 5, strtoupper( self::UUID ) ) );
		$this->assertFalse( $repository->userOwnsPassword( 5, 'not-a-uuid' ) );
		$this->assertNull( $repository->findForUser( 6, self::UUID ) );
	}
}
