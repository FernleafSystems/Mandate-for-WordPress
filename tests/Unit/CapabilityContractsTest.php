<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityName;
use FernleafSystems\Wordpress\Plugin\Mandate\MetaCaps\MetaCapabilityRegistry;

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
					],
					'editor' => [
						'read'       => true,
						'EDIT_POSTS' => true,
					],
				]
			)
		);
	}

	public function testMetaCapabilityRegistryNormalizesFilteredCaps() :void {
		add_filter(
			'mandate_meta_capabilities',
			static function () :array {
				return [ 'Edit_Post', 'wpm custom meta', 'delete_post' => false ];
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

	public function testMetaCapabilityRegistryIgnoresNonArrayFilterOutput() :void {
		add_filter(
			'mandate_meta_capabilities',
			static fn() :string => 'not-a-list'
		);

		$this->assertArrayHasKey( 'delete_post', ( new MetaCapabilityRegistry() )->registered() );
	}
}
