<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper;

use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\ApplicationPasswordScoper\MetaCaps\MetaCapabilityRegistry;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	public const VERSION = '0.1.0';
	public const MENU_SLUG = 'application-password-scoper';

	public static function boot( string $pluginFile ) :void {
		( new self() )->register( $pluginFile );
	}

	private function register( string $pluginFile ) :void {
		$scopeRepository = new ScopeRepository();
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
