<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityName;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\MetaCaps\MetaCapabilityRegistry;

final class CapabilityContractsTest extends Wpm_Test_Case {

	public function testCapabilityNameNormalizesListAndMapInputsToSortedGrantedMap() :void {
		$this->assertSame(
			[
				'delete_posts'      => true,
				'edit_posts'        => true,
				'wpm_manage_widget' => true,
			],
			CapabilityName::normalizeMap(
				[
					'EDIT_POSTS',
					'delete_posts'      => true,
					'manage_options'    => false,
					'wpm_manage_widget' => 1,
					[],
					'',
				]
			)
		);
	}

	public function testCapabilityNameRejectsNamesThatWouldBecomeIntegerArrayKeys() :void {
		$this->assertSame( '', CapabilityName::normalize( '0' ) );
		$this->assertSame( '', CapabilityName::normalize( '123' ) );
		$this->assertSame( '', CapabilityName::normalize( '-1' ) );
		$this->assertSame( '08', CapabilityName::normalize( '08' ) );
		$this->assertSame( 'level_0', CapabilityName::normalize( 'LEVEL_0' ) );

		$normalized = CapabilityName::normalizeMap( [ '0', '123', '-1', '08', 'LEVEL_0', 'read' ] );

		$this->assertSame(
			[
				'08'      => true,
				'level_0' => true,
				'read'    => true,
			],
			$normalized
		);
		foreach ( array_keys( $normalized ) as $capability ) {
			$this->assertIsString( $capability );
		}
	}

	public function testCandidateProviderNormalizesSortsAndMergesGrantedRoleCaps() :void {
		$provider = new CapabilityCandidateProvider();

		$this->assertSame(
			[
				'edit_posts'        => true,
				'read'              => true,
				'wpm_manage_widget' => true,
			],
			$provider->fromRoleCapabilities(
				[
					'custom' => [
						'wpm_manage_widget' => true,
						'delete_posts'      => false,
						'123'               => true,
					],
					'editor' => [
						'read'       => true,
						'EDIT_POSTS' => true,
						'-1'         => true,
					],
				]
			)
		);
	}

	public function testMetaCapabilityRegistryNormalizesFilteredCaps() :void {
		add_filter(
			'mdpsc_meta_capabilities',
			static function () :array {
				return [ 'Edit_Post', '123', '-1', 'wpm custom meta', 'delete_post' => false ];
			}
		);

		$registry = new MetaCapabilityRegistry();

		$this->assertSame(
			[
				'edit_post'     => true,
				'wpmcustommeta' => true,
			],
			$registry->registered()
		);
		$this->assertTrue( $registry->isRegistered( 'EDIT_POST' ) );
		$this->assertSame( [ 'edit_post' => true ], $registry->intersectSubmitted( [ 'edit_post', 'delete_post' ] ) );
	}

	public function testCapabilityGroupingIgnoresIntegerKeyProducingCapabilityNames() :void {
		$groups = ( new CapabilityGroupProvider() )->group(
			[ 'read', '123', '-1' ],
			[ 'edit_post', '0' ]
		);

		$this->assertSame( [ 'edit_post', 'read' ], array_column( $groups[ 'items' ], 'name' ) );
		foreach ( $groups[ 'items' ] as $item ) {
			$this->assertIsString( $item[ 'name' ] );
		}
	}

	public function testMetaCapabilityRegistryIgnoresNonArrayFilterOutput() :void {
		add_filter(
			'mdpsc_meta_capabilities',
			static fn() :string => 'not-a-list'
		);

		$this->assertArrayHasKey( 'delete_post', ( new MetaCapabilityRegistry() )->registered() );
	}
}
