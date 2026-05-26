<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate\Capabilities;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @phpstan-type CapabilitySource 'wordpress'|'third_party'
 * @phpstan-type CapabilityArea 'posts'|'pages'|'media'|'taxonomy'|'comments'|'users'|'plugins'|'themes'|'general'|'network'|'privacy'|'updates'|'legacy'|'third_party'
 * @phpstan-type CapabilityAction 'read'|'create'|'edit'|'delete'
 * @phpstan-type CapabilityDefinition array{area:CapabilityArea,action:CapabilityAction}
 * @phpstan-type CapabilityGroupItem array{name:string,type:'primitive'|'meta',source:CapabilitySource,area:CapabilityArea,action:CapabilityAction,known:bool}
 * @phpstan-type CapabilityGroupSection array{key:string,items:list<CapabilityGroupItem>}
 * @phpstan-type CapabilitySourceGroup array{key:CapabilitySource,items:list<CapabilityGroupItem>,area:list<CapabilityGroupSection>,action:list<CapabilityGroupSection>}
 * @phpstan-type CapabilityGroupingResult array{items:list<CapabilityGroupItem>,sources:list<CapabilitySourceGroup>}
 */
class CapabilityGroupProvider {

	public const MODE_AREA = 'area';
	public const MODE_ACTION = 'action';

	public const SOURCE_WORDPRESS = 'wordpress';
	public const SOURCE_THIRD_PARTY = 'third_party';

	public const AREA_POSTS = 'posts';
	public const AREA_PAGES = 'pages';
	public const AREA_MEDIA = 'media';
	public const AREA_TAXONOMY = 'taxonomy';
	public const AREA_COMMENTS = 'comments';
	public const AREA_USERS = 'users';
	public const AREA_PLUGINS = 'plugins';
	public const AREA_THEMES = 'themes';
	public const AREA_GENERAL = 'general';
	public const AREA_NETWORK = 'network';
	public const AREA_PRIVACY = 'privacy';
	public const AREA_UPDATES = 'updates';
	public const AREA_LEGACY = 'legacy';
	public const AREA_THIRD_PARTY = 'third_party';

	public const ACTION_READ = 'read';
	public const ACTION_CREATE = 'create';
	public const ACTION_EDIT = 'edit';
	public const ACTION_DELETE = 'delete';

	private const AREA_ORDER = [
		self::AREA_POSTS,
		self::AREA_PAGES,
		self::AREA_MEDIA,
		self::AREA_TAXONOMY,
		self::AREA_COMMENTS,
		self::AREA_USERS,
		self::AREA_PLUGINS,
		self::AREA_THEMES,
		self::AREA_GENERAL,
		self::AREA_NETWORK,
		self::AREA_PRIVACY,
		self::AREA_UPDATES,
		self::AREA_LEGACY,
		self::AREA_THIRD_PARTY,
	];

	private const ACTION_ORDER = [
		self::ACTION_READ,
		self::ACTION_CREATE,
		self::ACTION_EDIT,
		self::ACTION_DELETE,
	];

	private const SOURCE_ORDER = [
		self::SOURCE_WORDPRESS,
		self::SOURCE_THIRD_PARTY,
	];

	/**
	 * @var array<string,CapabilityDefinition>
	 */
	private const DEFINITIONS = [
		'activate_plugins'            => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_EDIT ],
		'add_users'                   => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_CREATE ],
		'assign_term'                 => [ 'area' => self::AREA_TAXONOMY, 'action' => self::ACTION_EDIT ],
		'create_sites'                => [ 'area' => self::AREA_NETWORK, 'action' => self::ACTION_CREATE ],
		'create_users'                => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_CREATE ],
		'customize'                   => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_EDIT ],
		'delete_others_pages'         => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_DELETE ],
		'delete_others_posts'         => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_DELETE ],
		'delete_page'                 => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_DELETE ],
		'delete_pages'                => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_DELETE ],
		'delete_plugins'              => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_DELETE ],
		'delete_post'                 => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_DELETE ],
		'delete_posts'                => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_DELETE ],
		'delete_private_pages'        => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_DELETE ],
		'delete_private_posts'        => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_DELETE ],
		'delete_published_pages'      => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_DELETE ],
		'delete_published_posts'      => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_DELETE ],
		'delete_site'                 => [ 'area' => self::AREA_NETWORK, 'action' => self::ACTION_DELETE ],
		'delete_sites'                => [ 'area' => self::AREA_NETWORK, 'action' => self::ACTION_DELETE ],
		'delete_term'                 => [ 'area' => self::AREA_TAXONOMY, 'action' => self::ACTION_DELETE ],
		'delete_themes'               => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_DELETE ],
		'delete_user'                 => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_DELETE ],
		'delete_users'                => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_DELETE ],
		'edit_comment'                => [ 'area' => self::AREA_COMMENTS, 'action' => self::ACTION_EDIT ],
		'edit_dashboard'              => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_EDIT ],
		'edit_files'                  => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_EDIT ],
		'edit_others_pages'           => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_EDIT ],
		'edit_others_posts'           => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_EDIT ],
		'edit_page'                   => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_EDIT ],
		'edit_pages'                  => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_EDIT ],
		'edit_plugins'                => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_EDIT ],
		'edit_post'                   => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_EDIT ],
		'edit_posts'                  => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_EDIT ],
		'edit_private_pages'          => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_EDIT ],
		'edit_private_posts'          => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_EDIT ],
		'edit_published_pages'        => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_EDIT ],
		'edit_published_posts'        => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_EDIT ],
		'edit_term'                   => [ 'area' => self::AREA_TAXONOMY, 'action' => self::ACTION_EDIT ],
		'edit_theme_options'          => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_EDIT ],
		'edit_themes'                 => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_EDIT ],
		'edit_user'                   => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_EDIT ],
		'edit_users'                  => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_EDIT ],
		'erase_others_personal_data'  => [ 'area' => self::AREA_PRIVACY, 'action' => self::ACTION_DELETE ],
		'export'                      => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_READ ],
		'export_others_personal_data' => [ 'area' => self::AREA_PRIVACY, 'action' => self::ACTION_READ ],
		'import'                      => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_CREATE ],
		'install_plugins'             => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_CREATE ],
		'install_themes'              => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_CREATE ],
		'level_0'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_1'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_2'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_3'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_4'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_5'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_6'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_7'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_8'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_9'                     => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'level_10'                    => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_READ ],
		'list_users'                  => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_READ ],
		'manage_categories'           => [ 'area' => self::AREA_TAXONOMY, 'action' => self::ACTION_EDIT ],
		'manage_links'                => [ 'area' => self::AREA_LEGACY, 'action' => self::ACTION_EDIT ],
		'manage_network'              => [ 'area' => self::AREA_NETWORK, 'action' => self::ACTION_EDIT ],
		'manage_network_options'      => [ 'area' => self::AREA_NETWORK, 'action' => self::ACTION_EDIT ],
		'manage_network_plugins'      => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_EDIT ],
		'manage_network_themes'       => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_EDIT ],
		'manage_network_users'        => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_EDIT ],
		'manage_options'              => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_EDIT ],
		'manage_privacy_options'      => [ 'area' => self::AREA_PRIVACY, 'action' => self::ACTION_EDIT ],
		'manage_sites'                => [ 'area' => self::AREA_NETWORK, 'action' => self::ACTION_EDIT ],
		'moderate_comments'           => [ 'area' => self::AREA_COMMENTS, 'action' => self::ACTION_EDIT ],
		'promote_users'               => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_EDIT ],
		'publish_pages'               => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_CREATE ],
		'publish_posts'               => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_CREATE ],
		'read'                        => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_READ ],
		'read_page'                   => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_READ ],
		'read_post'                   => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_READ ],
		'read_private_pages'          => [ 'area' => self::AREA_PAGES, 'action' => self::ACTION_READ ],
		'read_private_posts'          => [ 'area' => self::AREA_POSTS, 'action' => self::ACTION_READ ],
		'remove_users'                => [ 'area' => self::AREA_USERS, 'action' => self::ACTION_DELETE ],
		'resume_plugins'              => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_EDIT ],
		'resume_themes'               => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_EDIT ],
		'setup_network'               => [ 'area' => self::AREA_NETWORK, 'action' => self::ACTION_CREATE ],
		'switch_themes'               => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_EDIT ],
		'unfiltered_html'             => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_EDIT ],
		'unfiltered_upload'           => [ 'area' => self::AREA_MEDIA, 'action' => self::ACTION_CREATE ],
		'update_core'                 => [ 'area' => self::AREA_UPDATES, 'action' => self::ACTION_EDIT ],
		'update_languages'            => [ 'area' => self::AREA_UPDATES, 'action' => self::ACTION_EDIT ],
		'update_plugins'              => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_EDIT ],
		'update_themes'               => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_EDIT ],
		'upgrade_network'             => [ 'area' => self::AREA_UPDATES, 'action' => self::ACTION_EDIT ],
		'upload_files'                => [ 'area' => self::AREA_MEDIA, 'action' => self::ACTION_CREATE ],
		'upload_plugins'              => [ 'area' => self::AREA_PLUGINS, 'action' => self::ACTION_CREATE ],
		'upload_themes'               => [ 'area' => self::AREA_THEMES, 'action' => self::ACTION_CREATE ],
		'view_site_health_checks'     => [ 'area' => self::AREA_GENERAL, 'action' => self::ACTION_READ ],
	];

	/**
	 * @param array<string,true> $primitiveCaps
	 * @param array<string,true> $metaCaps
	 * @return CapabilityGroupingResult
	 */
	public function group( array $primitiveCaps, array $metaCaps ) :array {
		$items = array_merge(
			$this->itemsForCapabilities( 'primitive', $primitiveCaps ),
			$this->itemsForCapabilities( 'meta', $metaCaps )
		);

		$this->sortItems( $items );

		return [
			'items'   => $items,
			'sources' => $this->groupItemsBySource( $items ),
		];
	}

	/**
	 * @return list<string>
	 */
	public function sourceKeys() :array {
		return self::SOURCE_ORDER;
	}

	/**
	 * @return list<string>
	 */
	public function areaKeys() :array {
		return self::AREA_ORDER;
	}

	/**
	 * @return list<string>
	 */
	public function actionKeys() :array {
		return self::ACTION_ORDER;
	}

	/**
	 * @return array<string,CapabilityDefinition>
	 */
	public function definitions() :array {
		return self::DEFINITIONS;
	}

	/**
	 * @param 'primitive'|'meta' $type
	 * @param array<string,true> $capabilities
	 * @return list<CapabilityGroupItem>
	 */
	private function itemsForCapabilities( string $type, array $capabilities ) :array {
		$items = [];
		foreach ( array_keys( CapabilityName::normalizeMap( $capabilities ) ) as $capability ) {
			$definition = $this->definitionFor( $capability );
			$known = isset( self::DEFINITIONS[ $capability ] );
			$items[] = [
				'name'   => $capability,
				'type'   => $type,
				'source' => $known ? self::SOURCE_WORDPRESS : self::SOURCE_THIRD_PARTY,
				'area'   => $definition[ 'area' ],
				'action' => $definition[ 'action' ],
				'known'  => $known,
			];
		}

		return $items;
	}

	/**
	 * @return CapabilityDefinition
	 */
	private function definitionFor( string $capability ) :array {
		$capability = CapabilityName::normalize( $capability );
		return self::DEFINITIONS[ $capability ] ?? [
			'area'   => self::AREA_THIRD_PARTY,
			'action' => $this->fallbackActionFor( $capability ),
		];
	}

	private function fallbackActionFor( string $capability ) :string {
		foreach ( [ 'read', 'list', 'view', 'export' ] as $prefix ) {
			if ( $capability === $prefix || str_starts_with( $capability, $prefix.'_' ) ) {
				return self::ACTION_READ;
			}
		}

		foreach ( [ 'create', 'add', 'install', 'upload', 'import', 'publish' ] as $prefix ) {
			if ( $capability === $prefix || str_starts_with( $capability, $prefix.'_' ) ) {
				return self::ACTION_CREATE;
			}
		}

		foreach ( [ 'delete', 'remove', 'erase' ] as $prefix ) {
			if ( $capability === $prefix || str_starts_with( $capability, $prefix.'_' ) ) {
				return self::ACTION_DELETE;
			}
		}

		return self::ACTION_EDIT;
	}

	/**
	 * @param list<CapabilityGroupItem> $items
	 * @return list<CapabilitySourceGroup>
	 */
	private function groupItemsBySource( array $items ) :array {
		$grouped = [];
		foreach ( $items as $item ) {
			$grouped[ $item[ 'source' ] ][] = $item;
		}

		$sources = [];
		foreach ( self::SOURCE_ORDER as $source ) {
			$sourceItems = $grouped[ $source ] ?? [];
			$sources[] = [
				'key'    => $source,
				'items'  => $sourceItems,
				'area'   => $this->groupSourceItems( $sourceItems, self::MODE_AREA ),
				'action' => $this->groupSourceItems( $sourceItems, self::MODE_ACTION ),
			];
		}

		return $sources;
	}

	/**
	 * @param list<CapabilityGroupItem> $items
	 * @return list<CapabilityGroupSection>
	 */
	private function groupSourceItems( array $items, string $mode ) :array {
		$keys = $mode === self::MODE_AREA ? self::AREA_ORDER : self::ACTION_ORDER;
		$grouped = [];
		foreach ( $items as $item ) {
			$grouped[ $item[ $mode ] ][] = $item;
		}

		$sections = [];
		foreach ( $keys as $key ) {
			if ( isset( $grouped[ $key ] ) ) {
				$sections[] = [
					'key'   => $key,
					'items' => $grouped[ $key ],
				];
			}
		}

		return $sections;
	}

	/**
	 * @param list<CapabilityGroupItem> $items
	 */
	private function sortItems( array &$items ) :void {
		$sourcePositions = array_flip( self::SOURCE_ORDER );
		$areaPositions = array_flip( self::AREA_ORDER );
		$actionPositions = array_flip( self::ACTION_ORDER );
		usort(
			$items,
			static fn( array $a, array $b ) :int => [
				$sourcePositions[ $a[ 'source' ] ],
				$areaPositions[ $a[ 'area' ] ],
				$actionPositions[ $a[ 'action' ] ],
				$a[ 'type' ] === 'primitive' ? 0 : 1,
				$a[ 'name' ],
			] <=> [
				$sourcePositions[ $b[ 'source' ] ],
				$areaPositions[ $b[ 'area' ] ],
				$actionPositions[ $b[ 'action' ] ],
				$b[ 'type' ] === 'primitive' ? 0 : 1,
				$b[ 'name' ],
			]
		);
	}
}
