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
		'activate_plugins'           => 'Deselecting may prevent access to the Plugins screen and plugin activation.',
		'assign_term'                => 'Deselecting may prevent assigning terms while editing content.',
		'create_sites'               => 'Deselecting may prevent creating sites on a multisite network.',
		'create_users'               => 'Deselecting may prevent creating new users.',
		'customize'                  => 'Deselecting may prevent access to the WordPress Customizer.',
		'delete_others_pages'        => 'Deselecting may prevent deleting pages created by other users.',
		'delete_others_posts'        => 'Deselecting may prevent deleting posts created by other users.',
		'delete_page'                => 'Deselecting may prevent deleting a specific page when WordPress maps the request.',
		'delete_pages'               => 'Deselecting may prevent deleting pages.',
		'delete_plugins'             => 'Deselecting may prevent deleting installed plugins.',
		'delete_post'                => 'Deselecting may prevent deleting a specific post when WordPress maps the request.',
		'delete_posts'               => 'Deselecting may prevent deleting posts.',
		'delete_private_pages'       => 'Deselecting may prevent deleting private pages.',
		'delete_private_posts'       => 'Deselecting may prevent deleting private posts.',
		'delete_published_pages'     => 'Deselecting may prevent deleting published pages.',
		'delete_published_posts'     => 'Deselecting may prevent deleting published posts.',
		'delete_sites'               => 'Deselecting may prevent deleting sites on a multisite network.',
		'delete_site'                => 'Deselecting may prevent deleting the current multisite site.',
		'delete_term'                => 'Deselecting may prevent deleting a specific term when WordPress maps the request.',
		'delete_themes'              => 'Deselecting may prevent deleting installed themes.',
		'delete_user'                => 'Deselecting may prevent deleting a specific user when WordPress maps the request.',
		'delete_users'               => 'Deselecting may prevent deleting users.',
		'edit_dashboard'             => 'Deselecting may prevent editing dashboard widgets and dashboard options.',
		'edit_files'                 => 'Deselecting may affect legacy file editing checks.',
		'edit_others_pages'          => 'Deselecting may prevent editing pages created by other users.',
		'edit_others_posts'          => 'Deselecting may prevent editing posts created by other users.',
		'edit_page'                  => 'Deselecting may prevent editing a specific page when WordPress maps the request.',
		'edit_pages'                 => 'Deselecting may prevent access to create and edit pages.',
		'edit_plugins'               => 'Deselecting may prevent editing plugin files from wp-admin.',
		'edit_post'                  => 'Deselecting may prevent editing a specific post when WordPress maps the request.',
		'edit_posts'                 => 'Deselecting may prevent access to create and edit posts.',
		'edit_private_pages'         => 'Deselecting may prevent editing private pages.',
		'edit_private_posts'         => 'Deselecting may prevent editing private posts.',
		'edit_published_pages'       => 'Deselecting may prevent editing published pages.',
		'edit_published_posts'       => 'Deselecting may prevent editing published posts.',
		'edit_term'                  => 'Deselecting may prevent editing a specific term when WordPress maps the request.',
		'edit_theme_options'         => 'Deselecting may prevent managing widgets, menus, Customizer settings, and theme options.',
		'edit_themes'                => 'Deselecting may prevent editing theme files from wp-admin.',
		'edit_user'                  => 'Deselecting may prevent editing a specific user when WordPress maps the request.',
		'edit_users'                 => 'Deselecting may prevent access to edit users.',
		'erase_others_personal_data' => 'Deselecting may prevent erasing personal data for other users.',
		'export'                     => 'Deselecting may prevent exporting site content.',
		'export_others_personal_data' => 'Deselecting may prevent exporting personal data for other users.',
		'import'                     => 'Deselecting may prevent importing content.',
		'install_plugins'            => 'Deselecting may prevent installing plugins.',
		'install_themes'             => 'Deselecting may prevent installing themes.',
		'level_0'                    => 'Deselecting may affect legacy subscriber-level compatibility checks.',
		'level_1'                    => 'Deselecting may affect legacy contributor-level compatibility checks.',
		'level_2'                    => 'Deselecting may affect legacy author-level compatibility checks.',
		'level_3'                    => 'Deselecting may affect legacy author-level compatibility checks.',
		'level_4'                    => 'Deselecting may affect legacy editor-level compatibility checks.',
		'level_5'                    => 'Deselecting may affect legacy editor-level compatibility checks.',
		'level_6'                    => 'Deselecting may affect legacy administrator-level compatibility checks.',
		'level_7'                    => 'Deselecting may affect legacy administrator-level compatibility checks.',
		'level_8'                    => 'Deselecting may affect legacy administrator-level compatibility checks.',
		'level_9'                    => 'Deselecting may affect legacy administrator-level compatibility checks.',
		'level_10'                   => 'Deselecting may affect legacy administrator-level compatibility checks.',
		'list_users'                 => 'Deselecting may prevent viewing the Users screen.',
		'manage_categories'          => 'Deselecting may prevent managing post categories.',
		'manage_links'               => 'Deselecting may prevent managing links.',
		'manage_network'             => 'Deselecting may prevent access to network administration.',
		'manage_network_options'     => 'Deselecting may prevent managing network settings.',
		'manage_network_plugins'     => 'Deselecting may prevent managing network plugins.',
		'manage_network_themes'      => 'Deselecting may prevent managing network themes.',
		'manage_network_users'       => 'Deselecting may prevent managing network users.',
		'manage_options'             => 'Deselecting may prevent access to site settings.',
		'manage_privacy_options'     => 'Deselecting may prevent managing privacy settings.',
		'manage_sites'               => 'Deselecting may prevent managing sites on a multisite network.',
		'moderate_comments'          => 'Deselecting may prevent moderating comments.',
		'promote_users'              => 'Deselecting may prevent changing user roles.',
		'publish_pages'              => 'Deselecting may prevent publishing pages.',
		'publish_posts'              => 'Deselecting may prevent publishing posts.',
		'read'                       => 'Deselecting may prevent basic dashboard and profile access.',
		'read_page'                  => 'Deselecting may prevent reading a specific page when WordPress maps the request.',
		'read_post'                  => 'Deselecting may prevent reading a specific post when WordPress maps the request.',
		'read_private_pages'         => 'Deselecting may prevent reading private pages.',
		'read_private_posts'         => 'Deselecting may prevent reading private posts.',
		'remove_users'               => 'Deselecting may prevent removing users from the site.',
		'resume_plugins'             => 'Deselecting may prevent resuming paused plugins after errors.',
		'resume_themes'              => 'Deselecting may prevent resuming paused themes after errors.',
		'setup_network'              => 'Deselecting may prevent setting up a multisite network.',
		'switch_themes'              => 'Deselecting may prevent switching themes.',
		'unfiltered_html'            => 'Deselecting may prevent posting unfiltered HTML or JavaScript.',
		'unfiltered_upload'          => 'Deselecting may prevent uploads of file types WordPress normally filters.',
		'update_core'                => 'Deselecting may prevent updating WordPress core.',
		'update_languages'           => 'Deselecting may prevent updating translation files.',
		'update_plugins'             => 'Deselecting may prevent updating plugins.',
		'update_themes'              => 'Deselecting may prevent updating themes.',
		'upgrade_network'            => 'Deselecting may prevent running network upgrade tasks.',
		'upload_files'               => 'Deselecting may prevent access to upload or manage media files.',
		'upload_plugins'             => 'Deselecting may prevent uploading plugin ZIP files.',
		'upload_themes'              => 'Deselecting may prevent uploading theme ZIP files.',
		'view_site_health_checks'    => 'Deselecting may prevent viewing Site Health checks.',
	];

	public function descriptionFor( string $capability ) :string {
		$capability = CapabilityName::normalize( $capability );
		return $capability === '' ? '' : ( self::DESCRIPTIONS[ $capability ] ?? '' );
	}
}
