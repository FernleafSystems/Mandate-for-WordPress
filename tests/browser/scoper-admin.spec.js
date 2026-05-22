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

async function ensureSelectedOption( page, locator, value ) {
	if ( await locator.inputValue() === String( value ) ) {
		return;
	}

	await selectOptionAndWait( page, locator, value );
}

async function selectOptionWithDelayedNavigation( page, locator, value, expectedUrlPart ) {
	let delayed = false;
	await page.route( '**/wp-admin/tools.php?**', async ( route ) => {
		const request = route.request();
		if ( !delayed && request.isNavigationRequest() && request.url().includes( expectedUrlPart ) ) {
			delayed = true;
			await new Promise( ( resolve ) => setTimeout( resolve, 1000 ) );
		}
		await route.continue();
	} );

	try {
		const navigation = page.waitForNavigation( { waitUntil: 'load' } );
		const busyState = await locator.evaluate(
			( select, nextValue ) => {
				select.value = nextValue;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );

				const form = select.form;
				const status = form.querySelector( '[data-aps-selection-status]' );
				return {
					ariaBusy: form.getAttribute( 'aria-busy' ),
					selectedValue: select.value,
					statusHidden: status ? status.hidden : true,
				};
			},
			String( value )
		);
		expect( busyState ).toEqual( {
			ariaBusy: 'true',
			selectedValue: String( value ),
			statusHidden: false,
		} );
		await navigation;
	}
	finally {
		await page.unroute( '**/wp-admin/tools.php?**' );
	}
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

	await page.goto( '/wp-admin/tools.php?page=application-password-scoper', { waitUntil: 'load' } );
	const userSelect = page.locator( '#application-password-scoper-user' );
	await expect( userSelect ).toBeVisible();

	await selectOptionAndWait( page, userSelect, primary.user_id );
	await ensureSelectedOption( page, page.locator( '#application-password-scoper-password' ), primaryPassword.uuid );
	await expect( page.locator( '[data-aps-selection-form] button[type="submit"], [data-aps-selection-form] input[type="submit"]' ) ).toBeHidden();
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( 'Roles for selected user' );
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( primary.role_slug );
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( primary.role_name );
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( 'slug:' );
	await expect( page.locator( '#application-password-scoper-password' ) ).toHaveValue( primaryPassword.uuid );
	await expect( page.locator( '[data-aps-selection-form] #application-password-scoper-password-summary' ) ).toContainText( primaryPassword.uuid );

	await selectOptionWithDelayedNavigation(
		page,
		page.locator( '#application-password-scoper-password' ),
		secondaryPassword.uuid,
		`app_password_uuid=${secondaryPassword.uuid}`
	);
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBe( secondaryPassword.uuid );
	await expect( page.locator( '#application-password-scoper-password' ) ).toHaveValue( secondaryPassword.uuid );
	await expect( page.locator( '#application-password-scoper-password-summary' ) ).toContainText( secondaryPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#application-password-scoper-user' ), otherUser.user_id );
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBeNull();
	await expect( page.locator( '#application-password-scoper-role-summary' ) ).toContainText( otherUser.role_slug );
	await expect( page.locator( '#application-password-scoper-password' ) ).toHaveValue( otherPassword.uuid );
	await expect( page.locator( '#application-password-scoper-password-summary' ) ).toContainText( otherPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#application-password-scoper-user' ), primary.user_id );
	await ensureSelectedOption( page, page.locator( '#application-password-scoper-password' ), primaryPassword.uuid );
	await expect( page.locator( '#application-password-scoper-password' ) ).toHaveValue( primaryPassword.uuid );

	await expect( page.locator( '[data-aps-tab="wordpress"]' ) ).toHaveText( 'WordPress' );
	await expect( page.locator( '[data-aps-tab="other"]' ) ).toHaveText( 'Everything Else' );

	expect( new Set( await primitiveCapabilityValues( page, 'wordpress' ) ) ).toEqual( new Set( [
		'edit_posts',
		'read',
		'upload_files',
	] ) );
	expect( new Set( await primitiveCapabilityValues( page, 'other' ) ) ).toEqual( new Set( [ 'aps_manage_widget' ] ) );

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
