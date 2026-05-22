<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities;

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords\ApplicationPasswordRepository;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class ScopeRepository {

	public const OPTION_NAME = 'application_password_scoper_scopes';

	/**
	 * @return array<string,array{user_id:int,allowed_caps:array<string,true>,allowed_meta_caps:array<string,true>,updated_at:int,updated_by:int}>
	 */
	public function all() :array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, [] ) : [];
		if ( !is_array( $raw ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $raw as $uuid => $record ) {
			$uuid = ApplicationPasswordRepository::normalizeUuid( (string)$uuid );
			$record = is_array( $record ) ? $this->normalizeRecord( $record ) : null;
			if ( $uuid !== '' && $record !== null ) {
				$normalized[ $uuid ] = $record;
			}
		}

		return $normalized;
	}

	/**
	 * @return array{user_id:int,allowed_caps:array<string,true>,allowed_meta_caps:array<string,true>,updated_at:int,updated_by:int}|null
	 */
	public function find( string $uuid ) :?array {
		$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
		if ( $uuid === '' ) {
			return null;
		}

		$all = $this->all();
		return $all[ $uuid ] ?? null;
	}

	/**
	 * @param array<string,true> $allowedCaps
	 * @param array<string,true> $allowedMetaCaps
	 */
	public function save(
		string $uuid,
		int $userId,
		array $allowedCaps,
		array $allowedMetaCaps,
		int $updatedBy
	) :bool {
		$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
		if ( $uuid === '' || $userId < 1 ) {
			return false;
		}

		$all = $this->all();
		$all[ $uuid ] = [
			'user_id'           => $userId,
			'allowed_caps'      => CapabilityName::normalizeMap( $allowedCaps ),
			'allowed_meta_caps' => CapabilityName::normalizeMap( $allowedMetaCaps ),
			'updated_at'        => time(),
			'updated_by'        => max( 0, $updatedBy ),
		];

		return $this->persist( $all );
	}

	public function delete( string $uuid ) :bool {
		$uuid = ApplicationPasswordRepository::normalizeUuid( $uuid );
		if ( $uuid === '' ) {
			return false;
		}

		$all = $this->all();
		if ( !isset( $all[ $uuid ] ) ) {
			return true;
		}

		unset( $all[ $uuid ] );
		return $this->persist( $all );
	}

	/**
	 * @param array<string,mixed> $item
	 */
	public function deleteForApplicationPassword( int $userId, array $item ) :void {
		$uuid = isset( $item[ 'uuid' ] ) ? (string)$item[ 'uuid' ] : '';
		$this->delete( $uuid );
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array{user_id:int,allowed_caps:array<string,true>,allowed_meta_caps:array<string,true>,updated_at:int,updated_by:int}|null
	 */
	public function normalizeRecord( array $record ) :?array {
		$userId = isset( $record[ 'user_id' ] ) ? (int)$record[ 'user_id' ] : 0;
		if ( $userId < 1 ) {
			return null;
		}

		$allowedCaps = isset( $record[ 'allowed_caps' ] ) && is_array( $record[ 'allowed_caps' ] )
			? CapabilityName::normalizeMap( $record[ 'allowed_caps' ] )
			: [];
		$allowedMetaCaps = isset( $record[ 'allowed_meta_caps' ] ) && is_array( $record[ 'allowed_meta_caps' ] )
			? CapabilityName::normalizeMap( $record[ 'allowed_meta_caps' ] )
			: [];

		return [
			'user_id'           => $userId,
			'allowed_caps'      => $allowedCaps,
			'allowed_meta_caps' => $allowedMetaCaps,
			'updated_at'        => isset( $record[ 'updated_at' ] ) ? max( 0, (int)$record[ 'updated_at' ] ) : 0,
			'updated_by'        => isset( $record[ 'updated_by' ] ) ? max( 0, (int)$record[ 'updated_by' ] ) : 0,
		];
	}

	/**
	 * @param array<string,array<string,mixed>> $scopes
	 */
	private function persist( array $scopes ) :bool {
		if ( function_exists( 'update_option' ) ) {
			$updated = update_option( self::OPTION_NAME, $scopes, false );
			return (bool)$updated || get_option( self::OPTION_NAME, [] ) === $scopes;
		}

		return false;
	}
}
