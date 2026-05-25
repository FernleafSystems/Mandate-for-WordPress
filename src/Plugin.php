<?php

declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Mandate;

use FernleafSystems\Wordpress\Plugin\Mandate\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityDescriptionProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\Mandate\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ApplicationPasswordExpirationReaper;
use FernleafSystems\Wordpress\Plugin\Mandate\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\Mandate\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\Mandate\Options\PluginOptionsRepository;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	public const VERSION = '0.2.0';
	public const MENU_SLUG = 'mandate';

	public static function boot( string $pluginFile ) :void {
		( new self() )->register( $pluginFile );
	}

	private function register( string $pluginFile ) :void {
		$optionsRepository = new PluginOptionsRepository();
		$expirationDatePolicy = new ExpirationDatePolicy();
		$scopeRepository = new ScopeRepository( $optionsRepository, $expirationDatePolicy );
		$passwordRepository = new ApplicationPasswordRepository();
		$candidateProvider = new CapabilityCandidateProvider();
		$descriptionProvider = new CapabilityDescriptionProvider();
		$groupProvider = new CapabilityGroupProvider();
		$metaRegistry = new MetaCapabilityRegistry();
		$context = new CurrentApplicationPasswordContext();

		$adminPage = new AdminPage(
			$scopeRepository,
			$passwordRepository,
			$candidateProvider,
			$descriptionProvider,
			$metaRegistry,
			$groupProvider,
			$pluginFile,
			$expirationDatePolicy
		);
		$enforcer = new CapabilityScopeEnforcer(
			$scopeRepository,
			$candidateProvider,
			$context,
			$metaRegistry,
			$expirationDatePolicy
		);
		$expirationReaper = new ApplicationPasswordExpirationReaper( $scopeRepository, $expirationDatePolicy );

		$adminPage->registerHooks();
		$context->registerHooks();
		$enforcer->registerHooks();
		$expirationReaper->registerHooks();

		add_action( 'wp_delete_application_password', [ $scopeRepository, 'deleteForApplicationPassword' ], 10, 2 );
		if ( function_exists( 'register_deactivation_hook' ) ) {
			register_deactivation_hook( $pluginFile, [ ApplicationPasswordExpirationReaper::class, 'clearScheduledHook' ] );
		}
	}
}
