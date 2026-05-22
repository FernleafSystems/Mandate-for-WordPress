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

async function selectOptionAndWait( page, locator, value ) {
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		locator.selectOption( String( value ) ),
	] );
}

function primitiveCapInput( page, group, capability ) {
	return page.locator( `#application-password-scoper-${group}-primitive-capabilities input[name="allowed_caps[]"][value="${capability}"]` );
}

function anyPrimitiveCapInput( page, capability ) {
	return page.locator( `input[name="allowed_caps[]"][value="${capability}"]` );
}

async function primitiveCapabilityValues( page, group ) {
	return page.locator( `#application-password-scoper-${group}-primitive-capabilities input[name="allowed_caps[]"]` )
		.evaluateAll( ( inputs ) => inputs.map( ( input ) => input.value ) );
}

test( 'admin can manage tabbed application password scopes with progressive enhancement', async ( { page, baseURL } ) => {
	await loginAsAdmin( page );

	const fixtureResponse = await page.request.get( '/wp-json/application-password-scoper-test/v1/fixture' );
	expect( fixtureResponse.ok() ).toBeTruthy();
	const fixture = await fixtureResponse.json();
	const primary = fixture.primary;
	const otherUser = fixture.secondary_user;
	const primaryPassword = primary.passwords.primary;
	const secondaryPassword = primary.passwords.secondary;
	const otherPassword = otherUser.passwords.primary;

	const toolsMenuLink = page.locator( '#menu-tools a[href*="application-password-scoper"]' ).first();
	await expect( toolsMenuLink ).toBeVisible();
	await expect( toolsMenuLink ).toHaveAttribute( 'href', /tools\.php\?page=application-password-scoper/ );

	await page.goto( '/wp-admin/tools.php?page=application-password-scoper', { waitUntil: 'load' } );
	const userSelect = page.locator( '#application-password-scoper-user' );
	await expect( userSelect ).toBeVisible();

	await selectOptionAndWait( page, userSelect, primary.user_id );
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( primary.role_slug );
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( primary.role_name );
	await expect( page.locator( '#application-password-scoper-password' ) ).toHaveValue( primaryPassword.uuid );
	await expect( page.locator( '[data-aps-selection-form] #application-password-scoper-password-summary' ) ).toContainText( primaryPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#application-password-scoper-password' ), secondaryPassword.uuid );
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBe( secondaryPassword.uuid );
	await expect( page.locator( '#application-password-scoper-password-summary' ) ).toContainText( secondaryPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#application-password-scoper-user' ), otherUser.user_id );
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBeNull();
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( otherUser.role_slug );
	await expect( page.locator( '#application-password-scoper-password' ) ).toHaveValue( otherPassword.uuid );
	await expect( page.locator( '#application-password-scoper-password-summary' ) ).toContainText( otherPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#application-password-scoper-user' ), primary.user_id );
	await expect( page.locator( '#application-password-scoper-password' ) ).toHaveValue( primaryPassword.uuid );

	await expect( page.locator( '[data-aps-tab="wordpress"]' ) ).toHaveText( 'WordPress' );
	await expect( page.locator( '[data-aps-tab="other"]' ) ).toHaveText( 'Everything Else' );

	expect( await primitiveCapabilityValues( page, 'wordpress' ) ).toEqual( [
		'edit_posts',
		'read',
		'upload_files',
	] );
	expect( await primitiveCapabilityValues( page, 'other' ) ).toEqual( [ 'aps_manage_widget' ] );

	const wordpressLabels = await page.locator( '#application-password-scoper-wordpress-primitive-capabilities label' )
		.evaluateAll( ( labels ) => labels.map( ( label ) => label.getBoundingClientRect().top ) );
	expect( wordpressLabels[ 0 ] ).toBeLessThan( wordpressLabels[ 1 ] );
	expect( wordpressLabels[ 1 ] ).toBeLessThan( wordpressLabels[ 2 ] );

	await expect( anyPrimitiveCapInput( page, primary.direct_cap ) ).toHaveCount( 0 );
	await expect( anyPrimitiveCapInput( page, fixture.unassigned_role_cap ) ).toHaveCount( 0 );

	await page.locator( '[data-aps-panel="wordpress"] [data-aps-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'other', 'aps_manage_widget' ) ).toBeChecked();

	await page.locator( '[data-aps-panel="wordpress"] [data-aps-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();

	await page.locator( '[data-aps-tab="other"]' ).click();
	await page.locator( '[data-aps-panel="other"] [data-aps-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'other', 'aps_manage_widget' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();
	await page.locator( '[data-aps-panel="other"] [data-aps-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'other', 'aps_manage_widget' ) ).toBeChecked();

	await page.locator( '[data-aps-tab="wordpress"]' ).click();
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).uncheck();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="application_password_scoper_action"][value="save_scope"]' ).click(),
	] );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'edit_posts' ) ).toBeChecked();

	const scopedRequest = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			Authorization: basicAuthHeader( primary.user_login, primaryPassword.app_password ),
		},
	} );

	let capabilityResponse = await scopedRequest.get( '/wp-json/application-password-scoper-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	let capabilities = await capabilityResponse.json();
	expect( capabilities.user_id ).toBe( primary.user_id );
	expect( capabilities.read ).toBe( true );
	expect( capabilities.edit_posts ).toBe( true );
	expect( capabilities.upload_files ).toBe( false );
	expect( capabilities.delete_posts ).toBe( false );
	expect( capabilities.manage_options ).toBe( false );
	expect( capabilities.aps_manage_widget ).toBe( true );

	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="application_password_scoper_action"][value="clear_scope"]' ).click(),
	] );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();

	capabilityResponse = await scopedRequest.get( '/wp-json/application-password-scoper-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	capabilities = await capabilityResponse.json();
	expect( capabilities.upload_files ).toBe( true );
	expect( capabilities.delete_posts ).toBe( true );
	expect( capabilities.aps_manage_widget ).toBe( true );
	await scopedRequest.dispose();
} );
