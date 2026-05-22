const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/browser',
	timeout: 60_000,
	outputDir: './test-results/playwright',
	expect: {
		timeout: 10_000,
	},
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL: process.env.APS_BROWSER_BASE_URL || 'http://127.0.0.1:8898',
		headless: true,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: process.env.CI ? 'retain-on-failure' : 'off',
	},
} );
