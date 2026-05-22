const { test, expect, request } = require( '@playwright/test' );

async function loginAsAdmin( page ) {
	await page.goto( '/wp-admin/', { waitUntil: 'load' } );
	if ( await page.locator( '#loginform' ).count() ) {
		await page.locator( '#user_login' ).fill( process.env.APS_BROWSER_ADMIN_USER || 'admin' );
		await page.locator( '#user_pass' ).fill( process.env.APS_BROWSER_ADMIN_PASSWORD || 'password' );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
			page.locator( '#wp-submit' ).click(),
		] );
	}
}

function basicAuthHeader( username, password ) {
	return `Basic ${Buffer.from( `${username}:${password}` ).toString( 'base64' )}`;
}

function primitiveCapInput( page, capability ) {
	return page.locator( `#application-password-scoper-primitive-capabilities input[name="allowed_caps[]"][value="${capability}"]` );
}

test( 'admin can scope an application password to role-derived capabilities', async ( { page, baseURL } ) => {
	await loginAsAdmin( page );

	const fixtureResponse = await page.request.get( '/wp-json/application-password-scoper-test/v1/fixture' );
	expect( fixtureResponse.ok() ).toBeTruthy();
	const fixture = await fixtureResponse.json();

	const toolsMenuLink = page.locator( '#menu-tools a[href*="application-password-scoper"]' ).first();
	await expect( toolsMenuLink ).toBeVisible();
	await expect( toolsMenuLink ).toHaveAttribute( 'href', /tools\.php\?page=application-password-scoper/ );
	await page.goto( '/wp-admin/tools.php?page=application-password-scoper', { waitUntil: 'load' } );
	await expect( page.locator( '#application-password-scoper-user' ) ).toBeVisible();

	await page.locator( '#application-password-scoper-user' ).selectOption( String( fixture.user_id ) );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'form[method="get"] input[type="submit"]' ).click(),
	] );

	await page.locator( '#application-password-scoper-password' ).selectOption( fixture.uuid );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'form[method="get"] input[type="submit"]' ).click(),
	] );

	await expect( primitiveCapInput( page, 'read' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'edit_posts' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'upload_files' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'delete_posts' ) ).toHaveCount( 0 );
	await expect( primitiveCapInput( page, 'manage_options' ) ).toHaveCount( 0 );

	await primitiveCapInput( page, 'upload_files' ).uncheck();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="application_password_scoper_action"][value="save_scope"]' ).click(),
	] );
	await expect( primitiveCapInput( page, 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'edit_posts' ) ).toBeChecked();

	const scopedRequest = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			Authorization: basicAuthHeader( fixture.user_login, fixture.app_password ),
		},
	} );
	let capabilityResponse = await scopedRequest.get( '/wp-json/application-password-scoper-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	let capabilities = await capabilityResponse.json();
	expect( capabilities.user_id ).toBe( fixture.user_id );
	expect( capabilities.read ).toBe( true );
	expect( capabilities.edit_posts ).toBe( true );
	expect( capabilities.upload_files ).toBe( false );
	expect( capabilities.delete_posts ).toBe( false );
	expect( capabilities.manage_options ).toBe( false );

	await primitiveCapInput( page, 'upload_files' ).check();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="application_password_scoper_action"][value="save_scope"]' ).click(),
	] );
	await expect( primitiveCapInput( page, 'upload_files' ) ).toBeChecked();

	capabilityResponse = await scopedRequest.get( '/wp-json/application-password-scoper-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	capabilities = await capabilityResponse.json();
	expect( capabilities.upload_files ).toBe( true );
	await scopedRequest.dispose();
} );
