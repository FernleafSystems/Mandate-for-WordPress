<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityDescriptionProvider;

final class CapabilityDescriptionProviderTest extends Wpm_Test_Case {

	public function testKnownPrimitiveCapabilityReturnsDescription() :void {
		$this->assertSame(
			'Deselecting may prevent deleting posts.',
			( new CapabilityDescriptionProvider() )->descriptionFor( 'delete_posts' )
		);
	}

	public function testKnownMetaCapabilityReturnsDescription() :void {
		$this->assertSame(
			'Deselecting may prevent editing a specific post.',
			( new CapabilityDescriptionProvider() )->descriptionFor( 'edit_post' )
		);
	}

	public function testUnknownCustomCapabilityReturnsEmptyDescription() :void {
		$this->assertSame( '', ( new CapabilityDescriptionProvider() )->descriptionFor( 'wpm_manage_widget' ) );
	}

	public function testCapabilityNameIsNormalizedBeforeLookup() :void {
		$provider = new CapabilityDescriptionProvider();

		$this->assertSame(
			$provider->descriptionFor( 'delete_posts' ),
			$provider->descriptionFor( " \tDELETE_POSTS\n" )
		);
	}
}
