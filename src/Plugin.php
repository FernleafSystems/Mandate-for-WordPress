<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate;

use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	public const VERSION = '0.1.0';
	public const MENU_SLUG = 'mandate';

	public static function boot( string $pluginFile ) :void {
		( new self() )->register( $pluginFile );
	}

	private function register( string $pluginFile ) :void {
		$optionsRepository = new PluginOptionsRepository();
		$scopeRepository = new ScopeRepository( $optionsRepository );
		$passwordRepository = new ApplicationPasswordRepository();
		$candidateProvider = new CapabilityCandidateProvider();
		$groupProvider = new CapabilityGroupProvider();
		$metaRegistry = new MetaCapabilityRegistry();
		$context = new CurrentApplicationPasswordContext();

		$adminPage = new AdminPage(
			$scopeRepository,
			$passwordRepository,
			$candidateProvider,
			$metaRegistry,
			$groupProvider,
			$pluginFile
		);
		$enforcer = new CapabilityScopeEnforcer(
			$scopeRepository,
			$candidateProvider,
			$context,
			$metaRegistry
		);

		$adminPage->registerHooks();
		$context->registerHooks();
		$enforcer->registerHooks();

		add_action( 'wp_delete_application_password', [ $scopeRepository, 'deleteForApplicationPassword' ], 10, 2 );
	}
}
