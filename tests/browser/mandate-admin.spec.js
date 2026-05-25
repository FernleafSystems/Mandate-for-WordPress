const { test, expect, request } = require( '@playwright/test' );

async function loginAsAdmin( page ) {
	await page.goto( '/wp-admin/', { waitUntil: 'load' } );
	if ( await page.locator( '#loginform' ).count() ) {
		await page.locator( '#user_login' ).fill( process.env.WPM_BROWSER_ADMIN_USER || 'admin' );
		await page.locator( '#user_pass' ).fill( process.env.WPM_BROWSER_ADMIN_PASSWORD || 'password' );
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
				const status = form.querySelector( '[data-wpm-selection-status]' );
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
	return page.locator( `#mandate-${group}-primitive-capabilities input[name="allowed_caps[]"][value="${capability}"]` );
}

function primitiveCapTooltipTarget( page, group, capability ) {
	return page.locator( `#mandate-${group}-primitive-capabilities [data-wpm-tooltip]` ).filter( { hasText: capability } );
}

function anyPrimitiveCapInput( page, capability ) {
	return page.locator( `input[name="allowed_caps[]"][value="${capability}"]` );
}

async function primitiveCapabilityValues( page, group ) {
	return page.locator( `#mandate-${group}-primitive-capabilities input[name="allowed_caps[]"]` )
		.evaluateAll( ( inputs ) => inputs.map( ( input ) => input.value ) );
}

test( 'admin can manage tabbed application password scopes with progressive enhancement', async ( { page, baseURL } ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );
	await loginAsAdmin( page );

	const fixtureResponse = await page.request.get( '/wp-json/mandate-test/v1/fixture' );
	expect( fixtureResponse.ok() ).toBeTruthy();
	const fixture = await fixtureResponse.json();
	const primary = fixture.primary;
	const otherUser = fixture.secondary_user;
	const primaryPassword = primary.passwords.primary;
	const secondaryPassword = primary.passwords.secondary;
	const otherPassword = otherUser.passwords.primary;

	await page.goto( '/wp-admin/tools.php?page=mandate', { waitUntil: 'load' } );
	const userSelect = page.locator( '#mandate-user' );
	await expect( userSelect ).toBeVisible();

	await selectOptionAndWait( page, userSelect, primary.user_id );
	await ensureSelectedOption( page, page.locator( '#mandate-password' ), primaryPassword.uuid );
	await expect( page.locator( '#mandate-password' ) ).toHaveValue( primaryPassword.uuid );

	await selectOptionWithDelayedNavigation(
		page,
		page.locator( '#mandate-password' ),
		secondaryPassword.uuid,
		`app_password_uuid=${secondaryPassword.uuid}`
	);
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBe( secondaryPassword.uuid );
	await expect( page.locator( '#mandate-password' ) ).toHaveValue( secondaryPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#mandate-user' ), otherUser.user_id );
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBeNull();
	await expect( page.locator( '#mandate-password' ) ).toHaveValue( otherPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#mandate-user' ), primary.user_id );
	await ensureSelectedOption( page, page.locator( '#mandate-password' ), primaryPassword.uuid );
	await expect( page.locator( '#mandate-password' ) ).toHaveValue( primaryPassword.uuid );
	const selectionColumns = await page.locator( '.mandate-selection-grid > .mandate-selection-column' )
		.evaluateAll( ( columns ) => columns.map( ( column ) => {
			const rect = column.getBoundingClientRect();
			return {
				left: Math.round( rect.left ),
				top: Math.round( rect.top ),
				right: Math.round( rect.right ),
			};
		} ) );
	expect( selectionColumns ).toHaveLength( 3 );
	expect( Math.abs( selectionColumns[ 0 ].top - selectionColumns[ 1 ].top ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( selectionColumns[ 1 ].top - selectionColumns[ 2 ].top ) ).toBeLessThanOrEqual( 2 );
	expect( selectionColumns[ 0 ].right ).toBeLessThanOrEqual( selectionColumns[ 1 ].left );
	expect( selectionColumns[ 1 ].right ).toBeLessThanOrEqual( selectionColumns[ 2 ].left );
	const selectionTitleTops = await page.locator( '.mandate-selection-grid' ).evaluate( ( grid ) => (
		Array.from( grid.querySelectorAll( '.mandate-field-title' ) )
			.map( ( title ) => Math.round( title.getBoundingClientRect().top ) )
	) );
	expect( selectionTitleTops ).toHaveLength( 3 );
	expect( Math.abs( selectionTitleTops[ 0 ] - selectionTitleTops[ 1 ] ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( selectionTitleTops[ 1 ] - selectionTitleTops[ 2 ] ) ).toBeLessThanOrEqual( 2 );
	const summaryCardStyles = await page.locator( '#mandate-role-summary' ).evaluate( ( roleSummary ) => {
		const passwordSummary = document.querySelector( '#mandate-password-summary' );
		const properties = [
			'backgroundColor',
			'borderTopColor',
			'borderTopStyle',
			'borderTopWidth',
			'borderTopLeftRadius',
			'boxSizing',
			'paddingTop',
			'paddingRight',
			'paddingBottom',
			'paddingLeft',
		];

		return properties.map( ( property ) => [
			getComputedStyle( roleSummary )[ property ],
			getComputedStyle( passwordSummary )[ property ],
		] );
	} );
	summaryCardStyles.forEach( ( [ roleValue, passwordValue ] ) => {
		expect( roleValue ).toBe( passwordValue );
	} );
	await page.setViewportSize( { width: 760, height: 900 } );
	const mobileSelectionColumns = await page.locator( '.mandate-selection-grid > .mandate-selection-column' )
		.evaluateAll( ( columns ) => columns.map( ( column ) => {
			const rect = column.getBoundingClientRect();
			return {
				left: Math.round( rect.left ),
				top: Math.round( rect.top ),
			};
		} ) );
	expect( mobileSelectionColumns ).toHaveLength( 3 );
	expect( Math.abs( mobileSelectionColumns[ 0 ].left - mobileSelectionColumns[ 1 ].left ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( mobileSelectionColumns[ 1 ].left - mobileSelectionColumns[ 2 ].left ) ).toBeLessThanOrEqual( 2 );
	expect( mobileSelectionColumns[ 0 ].top ).toBeLessThan( mobileSelectionColumns[ 1 ].top );
	expect( mobileSelectionColumns[ 1 ].top ).toBeLessThan( mobileSelectionColumns[ 2 ].top );
	await page.setViewportSize( { width: 1280, height: 900 } );

	const activeTabColors = await page.locator( '[data-wpm-tab="wordpress"]' ).evaluate( ( tab ) => {
		const panel = document.querySelector( '[data-wpm-panel="wordpress"]' );
		return {
			tab: getComputedStyle( tab ).backgroundColor,
			panel: getComputedStyle( panel ).backgroundColor,
		};
	} );
	expect( activeTabColors.tab ).toBe( activeTabColors.panel );

	const capabilitySectionLayout = await page.locator( '[data-wpm-panel="wordpress"]' ).evaluate( ( panel ) => {
		const primitive = panel.querySelector( '#mandate-wordpress-primitive-capabilities' ).getBoundingClientRect();
		const meta = panel.querySelector( '#mandate-wordpress-meta-capabilities' ).getBoundingClientRect();
		return {
			primitiveTop: Math.round( primitive.top ),
			primitiveRight: Math.round( primitive.right ),
			metaTop: Math.round( meta.top ),
			metaLeft: Math.round( meta.left ),
		};
	} );
	expect( Math.abs( capabilitySectionLayout.primitiveTop - capabilitySectionLayout.metaTop ) ).toBeLessThanOrEqual( 2 );
	expect( capabilitySectionLayout.primitiveRight ).toBeLessThanOrEqual( capabilitySectionLayout.metaLeft );
	await expect( page.locator( '#mandate-password-summary [data-wpm-expiration-input]' ) ).toHaveCount( 1 );
	await expect( page.locator( '#mandate-scope-form [data-wpm-expiration-input]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toHaveValue( '' );
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toBeHidden();
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toBeVisible();
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toHaveAttribute( 'data-wpm-expiration-state', 'never' );
	await page.locator( '[data-wpm-expiration-summary]' ).click();
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toBeHidden();
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toBeVisible();
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toBeFocused();

	await page.locator( '[data-wpm-expiration-input]' ).fill( fixture.expiration_dates.future );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="mandate_action"][value="save_scope"]' ).click(),
	] );
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toHaveValue( fixture.expiration_dates.future );
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toBeHidden();
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toBeVisible();
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toHaveAttribute( 'data-wpm-expiration-state', 'date' );

	expect( new Set( await primitiveCapabilityValues( page, 'wordpress' ) ) ).toEqual( new Set( [
		'edit_posts',
		'read',
		'upload_files',
	] ) );
	expect( new Set( await primitiveCapabilityValues( page, 'other' ) ) ).toEqual( new Set( [ 'wpm_manage_widget' ] ) );

	await expect( anyPrimitiveCapInput( page, primary.direct_cap ) ).toHaveCount( 0 );
	await expect( anyPrimitiveCapInput( page, fixture.unassigned_role_cap ) ).toHaveCount( 0 );

	const uploadFilesName = primitiveCapTooltipTarget( page, 'wordpress', 'upload_files' );
	await expect( uploadFilesName ).toHaveAttribute( 'data-wpm-tooltip', '' );
	await expect( primitiveCapTooltipTarget( page, 'other', 'wpm_manage_widget' ) ).toHaveCount( 0 );

	const scopedRequest = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			Authorization: basicAuthHeader( primary.user_login, primaryPassword.app_password ),
		},
	} );

	let capabilityResponse = await scopedRequest.get( '/wp-json/mandate-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	let capabilities = await capabilityResponse.json();
	expect( capabilities.user_id ).toBe( primary.user_id );
	expect( capabilities.read ).toBe( true );
	expect( capabilities.edit_posts ).toBe( true );
	expect( capabilities.upload_files ).toBe( true );
	expect( capabilities.delete_posts ).toBe( true );
	expect( capabilities.wpm_manage_widget ).toBe( true );

	await page.locator( '[data-wpm-panel="wordpress"] [data-wpm-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'other', 'wpm_manage_widget' ) ).toBeChecked();

	await page.locator( '[data-wpm-panel="wordpress"] [data-wpm-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();

	await page.locator( '[data-wpm-tab="other"]' ).click();
	await page.locator( '[data-wpm-panel="other"] [data-wpm-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'other', 'wpm_manage_widget' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();
	await page.locator( '[data-wpm-panel="other"] [data-wpm-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'other', 'wpm_manage_widget' ) ).toBeChecked();

	await page.locator( '[data-wpm-tab="wordpress"]' ).click();
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).uncheck();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="mandate_action"][value="save_scope"]' ).click(),
	] );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'edit_posts' ) ).toBeChecked();

	capabilityResponse = await scopedRequest.get( '/wp-json/mandate-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	capabilities = await capabilityResponse.json();
	expect( capabilities.user_id ).toBe( primary.user_id );
	expect( capabilities.read ).toBe( true );
	expect( capabilities.edit_posts ).toBe( true );
	expect( capabilities.upload_files ).toBe( false );
	expect( capabilities.delete_posts ).toBe( false );
	expect( capabilities.manage_options ).toBe( false );
	expect( capabilities.wpm_manage_widget ).toBe( true );

	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="mandate_action"][value="clear_scope"]' ).click(),
	] );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toHaveValue( '' );
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toBeHidden();
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toBeVisible();
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toHaveAttribute( 'data-wpm-expiration-state', 'never' );

	capabilityResponse = await scopedRequest.get( '/wp-json/mandate-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	capabilities = await capabilityResponse.json();
	expect( capabilities.upload_files ).toBe( true );
	expect( capabilities.delete_posts ).toBe( true );
	expect( capabilities.wpm_manage_widget ).toBe( true );
	await scopedRequest.dispose();

	const secondaryRequest = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			Authorization: basicAuthHeader( primary.user_login, secondaryPassword.app_password ),
		},
	} );
	const expirationResponse = await page.request.post(
		'/wp-json/mandate-test/v1/expiration',
		{
			data: {
				user_id: primary.user_id,
				uuid: secondaryPassword.uuid,
				expires_on: fixture.expiration_dates.expired,
			},
		}
	);
	expect( expirationResponse.ok() ).toBeTruthy();
	expect( ( await expirationResponse.json() ).saved ).toBe( true );
	await page.goto( `/wp-admin/tools.php?page=mandate&user_id=${primary.user_id}&app_password_uuid=${secondaryPassword.uuid}`, { waitUntil: 'load' } );
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toHaveAttribute( 'data-wpm-expiration-state', 'expired' );
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toHaveText( `${fixture.expiration_dates.expired} (expired)` );
	await expect( page.locator( '[data-wpm-expiration-summary]' ) ).toHaveCSS( 'color', 'rgb(179, 45, 46)' );

	let authResponse = await secondaryRequest.get( '/wp-json/mandate-test/v1/auth' );
	expect( authResponse.ok() ).toBeTruthy();
	expect( ( await authResponse.json() ).user_id ).toBe( primary.user_id );
	capabilityResponse = await secondaryRequest.get( '/wp-json/mandate-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	capabilities = await capabilityResponse.json();
	expect( capabilities.read ).toBe( false );
	expect( capabilities.edit_posts ).toBe( false );
	expect( capabilities.upload_files ).toBe( false );
	expect( capabilities.delete_posts ).toBe( false );
	expect( capabilities.manage_options ).toBe( false );
	expect( capabilities.wpm_manage_widget ).toBe( false );

	const cronResponse = await page.request.post(
		'/wp-json/mandate-test/v1/run-expiration-cron',
		{
			data: {
				user_id: primary.user_id,
				uuid: secondaryPassword.uuid,
			},
		}
	);
	expect( cronResponse.ok() ).toBeTruthy();
	expect( ( await cronResponse.json() ).password_exists ).toBe( false );
	authResponse = await secondaryRequest.get( '/wp-json/mandate-test/v1/auth' );
	expect( authResponse.ok() ).toBeFalsy();
	await secondaryRequest.dispose();
} );
