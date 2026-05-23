<?php

declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityDescriptionProvider;

final class CapabilityDescriptionProviderTest extends Wpm_Test_Case {

	public function testKnownPrimitiveCapabilityReturnsDescription() :void {
		$this->assertNotSame( '', ( new CapabilityDescriptionProvider() )->descriptionFor( 'upload_files' ) );
	}

	public function testKnownMetaCapabilityReturnsDescription() :void {
		$this->assertNotSame( '', ( new CapabilityDescriptionProvider() )->descriptionFor( 'edit_post' ) );
	}

	public function testUnknownCustomCapabilityReturnsEmptyDescription() :void {
		$this->assertSame( '', ( new CapabilityDescriptionProvider() )->descriptionFor( 'wpm_manage_widget' ) );
	}

	public function testCapabilityNameIsNormalizedBeforeLookup() :void {
		$provider = new CapabilityDescriptionProvider();

		$this->assertSame(
			$provider->descriptionFor( 'upload_files' ),
			$provider->descriptionFor( " \tUPLOAD_FILES\n" )
		);
	}
}
