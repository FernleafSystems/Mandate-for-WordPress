<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Options;

use FernleafSystems\Wordpress\Plugin\Mandate\Plugin;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-type PluginOptionsMetadata array{schema_version:int,plugin_version:string,created_at:int,updated_at:int}
 * @phpstan-type PluginOptionsDocument array{metadata:PluginOptionsMetadata,scopes:array<string,mixed>}
 */
class PluginOptionsRepository {

	public const OPTION_NAME = 'aptoweb_mandate_application_password_scoper_options';
	public const CURRENT_SCHEMA_VERSION = 3;

	/**
	 * @return PluginOptionsDocument
	 */
	public function document() :array {
		return $this->readDocument() ?? $this->emptyDocument( time() );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function scopes() :array {
		return $this->document()[ 'scopes' ];
	}

	/**
	 * @param array<string,array<string,mixed>> $scopes
	 */
	public function replaceScopes( array $scopes ) :bool {
		$now = time();
		$document = $this->readDocument() ?? $this->emptyDocument( $now );
		$createdAt = $document[ 'metadata' ][ 'created_at' ] > 0
			? $document[ 'metadata' ][ 'created_at' ]
			: $now;
		$updatedDocument = [
			'metadata' => [
				'schema_version' => self::CURRENT_SCHEMA_VERSION,
				'plugin_version' => Plugin::VERSION,
				'created_at'     => $createdAt,
				'updated_at'     => $now,
			],
			'scopes'   => $scopes,
		];

		if ( function_exists( 'update_option' ) ) {
			$updated = update_option( self::OPTION_NAME, $updatedDocument, false );
			return (bool)$updated || get_option( self::OPTION_NAME, [] ) === $updatedDocument;
		}

		return false;
	}

	/**
	 * @return PluginOptionsDocument|null
	 */
	private function readDocument() :?array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, [] ) : [];
		return is_array( $raw ) ? $this->normalizeDocument( $raw ) : null;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return PluginOptionsDocument|null
	 */
	private function normalizeDocument( array $raw ) :?array {
		$schemaVersion = $this->schemaVersion( $raw );
		return match ( $schemaVersion ) {
			1, 2, self::CURRENT_SCHEMA_VERSION => $this->normalizeVersionedDocument( $raw ),
			default => null,
		};
	}

	/**
	 * @param array<string,mixed> $raw
	 */
	private function schemaVersion( array $raw ) :int {
		if ( !isset( $raw[ 'metadata' ] ) || !is_array( $raw[ 'metadata' ] ) ) {
			return 0;
		}

		$version = $raw[ 'metadata' ][ 'schema_version' ] ?? 0;
		return is_numeric( $version ) ? (int)$version : 0;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return PluginOptionsDocument|null
	 */
	private function normalizeVersionedDocument( array $raw ) :?array {
		if ( !isset( $raw[ 'metadata' ], $raw[ 'scopes' ] )
			|| !is_array( $raw[ 'metadata' ] )
			|| !is_array( $raw[ 'scopes' ] )
		) {
			return null;
		}

		$metadata = $this->normalizeMetadata( $raw[ 'metadata' ] );
		if ( $metadata === null ) {
			return null;
		}

		return [
			'metadata' => $metadata,
			'scopes'   => $raw[ 'scopes' ],
		];
	}

	/**
	 * @param array<string,mixed> $metadata
	 * @return PluginOptionsMetadata|null
	 */
	private function normalizeMetadata( array $metadata ) :?array {
		foreach ( [ 'schema_version', 'plugin_version', 'created_at', 'updated_at' ] as $key ) {
			if ( !array_key_exists( $key, $metadata ) ) {
				return null;
			}
		}
		if ( !is_scalar( $metadata[ 'plugin_version' ] )
			|| !is_numeric( $metadata[ 'schema_version' ] )
			|| !is_numeric( $metadata[ 'created_at' ] )
			|| !is_numeric( $metadata[ 'updated_at' ] )
		) {
			return null;
		}

		return [
			'schema_version' => (int)$metadata[ 'schema_version' ],
			'plugin_version' => (string)$metadata[ 'plugin_version' ],
			'created_at'     => max( 0, (int)$metadata[ 'created_at' ] ),
			'updated_at'     => max( 0, (int)$metadata[ 'updated_at' ] ),
		];
	}

	/**
	 * @return PluginOptionsDocument
	 */
	private function emptyDocument( int $now ) :array {
		return [
			'metadata' => [
				'schema_version' => self::CURRENT_SCHEMA_VERSION,
				'plugin_version' => Plugin::VERSION,
				'created_at'     => $now,
				'updated_at'     => $now,
			],
			'scopes'   => [],
		];
	}
}
