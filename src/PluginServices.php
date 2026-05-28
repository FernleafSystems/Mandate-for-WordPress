<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\MandateAppSecurity;

use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminPage;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminPageViewDataBuilder;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminProfileContext;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminRequest;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminScopeAccessPolicy;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminScopeFormSecurity;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminTemplateRenderer;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminTrustedHtmlSanitizer;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\AdminUserRoleProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Admin\ApplicationPasswordScopeColumn;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\ApplicationPasswords\ApplicationPasswordRepository;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\ApplicationPasswords\CurrentApplicationPasswordContext;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityCandidateProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityDescriptionProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityGroupProvider;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\CapabilityScopeEnforcer;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Capabilities\ScopeRepository;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Expiration\ApplicationPasswordExpirationReaper;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Expiration\ExpirationDatePolicy;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\MetaCaps\MetaCapabilityRegistry;
use FernleafSystems\Wordpress\Plugin\MandateAppSecurity\Options\PluginOptionsRepository;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

final class PluginServices {

	private ?PluginOptionsRepository $optionsRepository = null;

	private ?ExpirationDatePolicy $expirationDatePolicy = null;

	private ?ScopeRepository $scopeRepository = null;

	private ?ApplicationPasswordRepository $passwordRepository = null;

	private ?CapabilityCandidateProvider $capabilityCandidateProvider = null;

	private ?CapabilityDescriptionProvider $capabilityDescriptionProvider = null;

	private ?CapabilityGroupProvider $capabilityGroupProvider = null;

	private ?MetaCapabilityRegistry $metaCapabilityRegistry = null;

	private ?CurrentApplicationPasswordContext $currentApplicationPasswordContext = null;

	private ?CapabilityScopeEnforcer $capabilityScopeEnforcer = null;

	private ?ApplicationPasswordExpirationReaper $expirationReaper = null;

	private ?AdminRequest $adminRequest = null;

	private ?AdminScopeAccessPolicy $adminScopeAccessPolicy = null;

	private ?AdminProfileContext $adminProfileContext = null;

	private ?AdminPage $adminPage = null;

	private ?ApplicationPasswordScopeColumn $applicationPasswordScopeColumn = null;

	public function __construct( private string $pluginFile ) {
	}

	public function pluginFile() :string {
		return $this->pluginFile;
	}

	public function optionsRepository() :PluginOptionsRepository {
		return $this->optionsRepository ??= new PluginOptionsRepository();
	}

	public function expirationDatePolicy() :ExpirationDatePolicy {
		return $this->expirationDatePolicy ??= new ExpirationDatePolicy();
	}

	public function scopeRepository() :ScopeRepository {
		return $this->scopeRepository ??= new ScopeRepository( $this->optionsRepository(), $this->expirationDatePolicy() );
	}

	public function passwordRepository() :ApplicationPasswordRepository {
		return $this->passwordRepository ??= new ApplicationPasswordRepository();
	}

	public function capabilityCandidateProvider() :CapabilityCandidateProvider {
		return $this->capabilityCandidateProvider ??= new CapabilityCandidateProvider();
	}

	public function capabilityDescriptionProvider() :CapabilityDescriptionProvider {
		return $this->capabilityDescriptionProvider ??= new CapabilityDescriptionProvider();
	}

	public function capabilityGroupProvider() :CapabilityGroupProvider {
		return $this->capabilityGroupProvider ??= new CapabilityGroupProvider();
	}

	public function metaCapabilityRegistry() :MetaCapabilityRegistry {
		return $this->metaCapabilityRegistry ??= new MetaCapabilityRegistry();
	}

	public function currentApplicationPasswordContext() :CurrentApplicationPasswordContext {
		return $this->currentApplicationPasswordContext ??= new CurrentApplicationPasswordContext();
	}

	public function capabilityScopeEnforcer() :CapabilityScopeEnforcer {
		return $this->capabilityScopeEnforcer ??= new CapabilityScopeEnforcer(
			$this->scopeRepository(),
			$this->capabilityCandidateProvider(),
			$this->currentApplicationPasswordContext(),
			$this->metaCapabilityRegistry(),
			$this->expirationDatePolicy()
		);
	}

	public function expirationReaper() :ApplicationPasswordExpirationReaper {
		return $this->expirationReaper ??= new ApplicationPasswordExpirationReaper(
			$this->scopeRepository(),
			$this->expirationDatePolicy()
		);
	}

	public function adminRequest() :AdminRequest {
		return $this->adminRequest ??= new AdminRequest();
	}

	public function adminScopeAccessPolicy() :AdminScopeAccessPolicy {
		return $this->adminScopeAccessPolicy ??= new AdminScopeAccessPolicy();
	}

	public function adminProfileContext() :AdminProfileContext {
		return $this->adminProfileContext ??= new AdminProfileContext(
			$this->adminRequest(),
			$this->adminScopeAccessPolicy()
		);
	}

	public function adminPage() :AdminPage {
		if ( $this->adminPage !== null ) {
			return $this->adminPage;
		}

		$trustedHtmlSanitizer = new AdminTrustedHtmlSanitizer();
		$formSecurity = new AdminScopeFormSecurity();
		$roleProvider = new AdminUserRoleProvider();
		$viewDataBuilder = new AdminPageViewDataBuilder(
			$this->adminRequest(),
			$this->scopeRepository(),
			$this->passwordRepository(),
			$this->capabilityCandidateProvider(),
			$this->capabilityDescriptionProvider(),
			$this->metaCapabilityRegistry(),
			$this->capabilityGroupProvider(),
			$this->expirationDatePolicy(),
			$roleProvider,
			$formSecurity,
			$trustedHtmlSanitizer,
			$this->adminScopeAccessPolicy()
		);

		return $this->adminPage = new AdminPage(
			$this->scopeRepository(),
			$this->passwordRepository(),
			$this->capabilityCandidateProvider(),
			$this->metaCapabilityRegistry(),
			$this->pluginFile(),
			$this->expirationDatePolicy(),
			$this->adminRequest(),
			$roleProvider,
			$formSecurity,
			$viewDataBuilder,
			new AdminTemplateRenderer(),
			$this->adminScopeAccessPolicy()
		);
	}

	public function applicationPasswordScopeColumn() :ApplicationPasswordScopeColumn {
		return $this->applicationPasswordScopeColumn ??= new ApplicationPasswordScopeColumn(
			$this->adminScopeAccessPolicy(),
			$this->adminProfileContext()
		);
	}
}
