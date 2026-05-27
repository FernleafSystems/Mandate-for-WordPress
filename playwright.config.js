const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/browser',
	timeout: 60_000,
	outputDir: process.env.WPM_BROWSER_OUTPUT_DIR || './test-results/playwright',
	expect: {
		timeout: 10_000,
	},
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		headless: true,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: process.env.CI ? 'retain-on-failure' : 'off',
	},
} );
