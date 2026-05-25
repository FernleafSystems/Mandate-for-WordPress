<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Capabilities;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CapabilityDescriptionProvider {

	/**
	 * @var array<string,string>
	 */
	private const DESCRIPTIONS = [
		'activate_plugins'           => 'Deselecting may prevent managing plugins.',
		'assign_term'                => 'Deselecting may prevent assigning terms to content.',
		'create_sites'               => 'Deselecting may prevent creating sites on a multisite network.',
		'create_users'               => 'Deselecting may prevent creating new users.',
		'customize'                  => 'Deselecting may prevent using the WordPress Customizer.',
		'delete_others_pages'        => 'Deselecting may prevent deleting pages created by other users.',
		'delete_others_posts'        => 'Deselecting may prevent deleting posts created by other users.',
		'delete_page'                => 'Deselecting may prevent deleting a specific page.',
		'delete_pages'               => 'Deselecting may prevent deleting pages.',
		'delete_plugins'             => 'Deselecting may prevent deleting installed plugins.',
		'delete_post'                => 'Deselecting may prevent deleting a specific post.',
		'delete_posts'               => 'Deselecting may prevent deleting posts.',
		'delete_private_pages'       => 'Deselecting may prevent deleting private pages.',
		'delete_private_posts'       => 'Deselecting may prevent deleting private posts.',
		'delete_published_pages'     => 'Deselecting may prevent deleting published pages.',
		'delete_published_posts'     => 'Deselecting may prevent deleting published posts.',
		'delete_sites'               => 'Deselecting may prevent deleting sites on a multisite network.',
		'delete_site'                => 'Deselecting may prevent deleting the current multisite site.',
		'delete_term'                => 'Deselecting may prevent deleting a specific term.',
		'delete_themes'              => 'Deselecting may prevent deleting installed themes.',
		'delete_user'                => 'Deselecting may prevent deleting a specific user.',
		'delete_users'               => 'Deselecting may prevent deleting users.',
		'edit_dashboard'             => 'Deselecting may prevent editing dashboard options.',
		'edit_files'                 => 'Deselecting may prevent legacy file editing.',
		'edit_others_pages'          => 'Deselecting may prevent editing pages created by other users.',
		'edit_others_posts'          => 'Deselecting may prevent editing posts created by other users.',
		'edit_page'                  => 'Deselecting may prevent editing a specific page.',
		'edit_pages'                 => 'Deselecting may prevent creating and editing pages.',
		'edit_plugins'               => 'Deselecting may prevent editing plugin files.',
		'edit_post'                  => 'Deselecting may prevent editing a specific post.',
		'edit_posts'                 => 'Deselecting may prevent creating and editing posts.',
		'edit_private_pages'         => 'Deselecting may prevent editing private pages.',
		'edit_private_posts'         => 'Deselecting may prevent editing private posts.',
		'edit_published_pages'       => 'Deselecting may prevent editing published pages.',
		'edit_published_posts'       => 'Deselecting may prevent editing published posts.',
		'edit_term'                  => 'Deselecting may prevent editing a specific term.',
		'edit_theme_options'         => 'Deselecting may prevent managing widgets, menus, Customizer settings, and theme options.',
		'edit_themes'                => 'Deselecting may prevent editing theme files.',
		'edit_user'                  => 'Deselecting may prevent editing a specific user.',
		'edit_users'                 => 'Deselecting may prevent editing users.',
		'erase_others_personal_data' => 'Deselecting may prevent erasing personal data for other users.',
		'export'                     => 'Deselecting may prevent exporting site content.',
		'export_others_personal_data' => 'Deselecting may prevent exporting personal data for other users.',
		'import'                     => 'Deselecting may prevent importing content.',
		'install_plugins'            => 'Deselecting may prevent installing plugins.',
		'install_themes'             => 'Deselecting may prevent installing themes.',
		'level_0'                    => 'Deselecting may prevent legacy subscriber-level checks.',
		'level_1'                    => 'Deselecting may prevent legacy contributor-level checks.',
		'level_2'                    => 'Deselecting may prevent legacy author-level checks.',
		'level_3'                    => 'Deselecting may prevent legacy author-level checks.',
		'level_4'                    => 'Deselecting may prevent legacy editor-level checks.',
		'level_5'                    => 'Deselecting may prevent legacy editor-level checks.',
		'level_6'                    => 'Deselecting may prevent legacy administrator-level checks.',
		'level_7'                    => 'Deselecting may prevent legacy administrator-level checks.',
		'level_8'                    => 'Deselecting may prevent legacy administrator-level checks.',
		'level_9'                    => 'Deselecting may prevent legacy administrator-level checks.',
		'level_10'                   => 'Deselecting may prevent legacy administrator-level checks.',
		'list_users'                 => 'Deselecting may prevent viewing the Users screen.',
		'manage_categories'          => 'Deselecting may prevent managing post categories.',
		'manage_links'               => 'Deselecting may prevent managing links.',
		'manage_network'             => 'Deselecting may prevent network administration.',
		'manage_network_options'     => 'Deselecting may prevent managing network settings.',
		'manage_network_plugins'     => 'Deselecting may prevent managing network plugins.',
		'manage_network_themes'      => 'Deselecting may prevent managing network themes.',
		'manage_network_users'       => 'Deselecting may prevent managing network users.',
		'manage_options'             => 'Deselecting may prevent managing site settings.',
		'manage_privacy_options'     => 'Deselecting may prevent managing privacy settings.',
		'manage_sites'               => 'Deselecting may prevent managing sites on a multisite network.',
		'moderate_comments'          => 'Deselecting may prevent moderating comments.',
		'promote_users'              => 'Deselecting may prevent changing user roles.',
		'publish_pages'              => 'Deselecting may prevent publishing pages.',
		'publish_posts'              => 'Deselecting may prevent publishing posts.',
		'read'                       => 'Deselecting may prevent dashboard and profile access.',
		'read_page'                  => 'Deselecting may prevent reading a specific page.',
		'read_post'                  => 'Deselecting may prevent reading a specific post.',
		'read_private_pages'         => 'Deselecting may prevent reading private pages.',
		'read_private_posts'         => 'Deselecting may prevent reading private posts.',
		'remove_users'               => 'Deselecting may prevent removing users from the site.',
		'resume_plugins'             => 'Deselecting may prevent resuming paused plugins after errors.',
		'resume_themes'              => 'Deselecting may prevent resuming paused themes after errors.',
		'setup_network'              => 'Deselecting may prevent setting up a multisite network.',
		'switch_themes'              => 'Deselecting may prevent switching themes.',
		'unfiltered_html'            => 'Deselecting may prevent posting unfiltered HTML or JavaScript.',
		'unfiltered_upload'          => 'Deselecting may prevent uploading unfiltered file types.',
		'update_core'                => 'Deselecting may prevent updating WordPress core.',
		'update_languages'           => 'Deselecting may prevent updating translation files.',
		'update_plugins'             => 'Deselecting may prevent updating plugins.',
		'update_themes'              => 'Deselecting may prevent updating themes.',
		'upgrade_network'            => 'Deselecting may prevent running network upgrade tasks.',
		'upload_files'               => 'Deselecting may prevent uploading and managing media files.',
		'upload_plugins'             => 'Deselecting may prevent uploading plugin ZIP files.',
		'upload_themes'              => 'Deselecting may prevent uploading theme ZIP files.',
		'view_site_health_checks'    => 'Deselecting may prevent viewing Site Health checks.',
	];

	public function descriptionFor( string $capability ) :string {
		$capability = CapabilityName::normalize( $capability );
		return $capability === '' ? '' : ( self::DESCRIPTIONS[ $capability ] ?? '' );
	}
}
