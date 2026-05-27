<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Tooling;

use FernleafSystems\Wordpress\Plugin\Mandate\PluginIdentity;

final class ReleasePackageIdentity {

	public const GITHUB_ASSET_PREFIX = PluginIdentity::SLUG.'-github';

	public static function zipName( string $variant, string $tag ) :string {
		$tag = \trim( $tag );
		if ( $tag === '' ) {
			throw new \RuntimeException( 'Release tag must not be empty.' );
		}

		return match ( $variant ) {
			RuntimePackageBuilder::VARIANT_WORDPRESS_ORG => PluginIdentity::SLUG.'-'.$tag.'.zip',
			RuntimePackageBuilder::VARIANT_GITHUB        => self::GITHUB_ASSET_PREFIX.'-'.$tag.'.zip',
			default                                     => throw new \RuntimeException( 'Unknown release package variant: '.$variant ),
		};
	}
}
