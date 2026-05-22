<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Capabilities;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CapabilityGroupProvider {

	private const WORDPRESS_PRIMITIVE_CAPABILITIES = [
		'activate_plugins',
		'create_sites',
		'create_users',
		'customize',
		'delete_others_pages',
		'delete_others_posts',
		'delete_pages',
		'delete_plugins',
		'delete_posts',
		'delete_private_pages',
		'delete_private_posts',
		'delete_published_pages',
		'delete_published_posts',
		'delete_sites',
		'delete_site',
		'delete_themes',
		'delete_users',
		'edit_dashboard',
		'edit_files',
		'edit_others_pages',
		'edit_others_posts',
		'edit_pages',
		'edit_plugins',
		'edit_posts',
		'edit_private_pages',
		'edit_private_posts',
		'edit_published_pages',
		'edit_published_posts',
		'edit_theme_options',
		'edit_themes',
		'edit_users',
		'erase_others_personal_data',
		'export',
		'export_others_personal_data',
		'import',
		'install_plugins',
		'install_themes',
		'level_0',
		'level_1',
		'level_2',
		'level_3',
		'level_4',
		'level_5',
		'level_6',
		'level_7',
		'level_8',
		'level_9',
		'level_10',
		'list_users',
		'manage_categories',
		'manage_links',
		'manage_network',
		'manage_network_options',
		'manage_network_plugins',
		'manage_network_themes',
		'manage_network_users',
		'manage_options',
		'manage_privacy_options',
		'manage_sites',
		'moderate_comments',
		'promote_users',
		'publish_pages',
		'publish_posts',
		'read',
		'read_private_pages',
		'read_private_posts',
		'remove_users',
		'resume_plugins',
		'resume_themes',
		'setup_network',
		'switch_themes',
		'unfiltered_html',
		'unfiltered_upload',
		'update_core',
		'update_languages',
		'update_plugins',
		'update_themes',
		'upgrade_network',
		'upload_files',
		'upload_plugins',
		'upload_themes',
		'view_site_health_checks',
	];

	private const WORDPRESS_META_CAPABILITIES = [
		'assign_term',
		'delete_page',
		'delete_post',
		'delete_term',
		'delete_user',
		'edit_page',
		'edit_post',
		'edit_term',
		'edit_user',
		'read_page',
		'read_post',
	];

	/**
	 * @param array<string,true> $primitiveCaps
	 * @param array<string,true> $metaCaps
	 * @return array{wordpress:array{primitive:array<string,true>,meta:array<string,true>},other:array{primitive:array<string,true>,meta:array<string,true>}}
	 */
	public function group( array $primitiveCaps, array $metaCaps ) :array {
		return [
			'wordpress' => [
				'primitive' => $this->filterAllowed( $primitiveCaps, self::WORDPRESS_PRIMITIVE_CAPABILITIES, true ),
				'meta'      => $this->filterAllowed( $metaCaps, self::WORDPRESS_META_CAPABILITIES, true ),
			],
			'other'     => [
				'primitive' => $this->filterAllowed( $primitiveCaps, self::WORDPRESS_PRIMITIVE_CAPABILITIES, false ),
				'meta'      => $this->filterAllowed( $metaCaps, self::WORDPRESS_META_CAPABILITIES, false ),
			],
		];
	}

	/**
	 * @param array<string,true> $capabilities
	 * @param string[] $allowlist
	 * @return array<string,true>
	 */
	private function filterAllowed( array $capabilities, array $allowlist, bool $allowed ) :array {
		$allowMap = array_fill_keys( $allowlist, true );
		$filtered = [];
		foreach ( CapabilityName::normalizeMap( $capabilities ) as $capability => $granted ) {
			if ( isset( $allowMap[ $capability ] ) === $allowed ) {
				$filtered[ $capability ] = $granted;
			}
		}

		ksort( $filtered, SORT_NATURAL );
		return $filtered;
	}
}
