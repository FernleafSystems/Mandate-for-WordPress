const { test, expect, request } = require( '@playwright/test' );

async function loginAsAdmin( page ) {
	await loginAs( page, process.env.WPM_BROWSER_ADMIN_USER || 'admin', process.env.WPM_BROWSER_ADMIN_PASSWORD || 'password' );
}

async function loginAs( page, username, password ) {
	await page.goto( '/wp-admin/', { waitUntil: 'load' } );
	if ( await page.locator( '#loginform' ).count() ) {
		await page.locator( '#user_login' ).fill( username );
		await page.locator( '#user_pass' ).fill( password );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
			page.locator( '#wp-submit' ).click(),
		] );
	}
}

async function loginAsFixtureUser( page, username ) {
	await page.context().clearCookies();
	await loginAs( page, username, 'password' );
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

function primitiveCapInput( page, source, capability ) {
	return page.locator( `[data-wpm-capability-source-panel][data-wpm-capability-source="${source}"] input[name="allowed_caps[]"][value="${capability}"]` );
}

function capabilitySection( page, source, mode, section ) {
	return page.locator( `#mandate-${source}-${mode}-${section}-capabilities` );
}

function primitiveCapTooltipTarget( page, source, capability ) {
	return page.locator( `[data-wpm-capability-source-panel][data-wpm-capability-source="${source}"] [data-wpm-tooltip]` ).filter( { hasText: capability } );
}

function anyPrimitiveCapInput( page, capability ) {
	return page.locator( `input[name="allowed_caps[]"][value="${capability}"]` );
}

async function primitiveCapabilityValues( page, source ) {
	return page.locator( `[data-wpm-capability-source-panel][data-wpm-capability-source="${source}"] input[name="allowed_caps[]"]` )
		.evaluateAll( ( inputs ) => inputs.map( ( input ) => input.value ) );
}

async function selectGroupingMode( page, mode ) {
	await page.locator( `input[name="capability_grouping_mode"][value="${mode}"]` ).check();
}

async function selectCapabilitySource( page, source ) {
	await page.locator( `[data-wpm-capability-source-tab][data-wpm-capability-source="${source}"]` ).click();
}

async function locatorRect( locator ) {
	return locator.evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return {
			left: Math.round( rect.left ),
			top: Math.round( rect.top ),
			right: Math.round( rect.right ),
			bottom: Math.round( rect.bottom ),
			width: Math.round( rect.width ),
		};
	} );
}

async function firstRowCapabilityColumns( section ) {
	return section.locator( '.mandate-capability-list label' ).evaluateAll( ( labels ) => {
		const positions = labels.map( ( label ) => {
			const rect = label.getBoundingClientRect();
			return {
				left: Math.round( rect.left ),
				top: Math.round( rect.top ),
			};
		} );
		const firstTop = positions[ 0 ].top;
		return positions.filter( ( position ) => Math.abs( position.top - firstTop ) <= 2 )
			.map( ( position ) => position.left );
	} );
}

test( 'admin can manage grouped application password scopes with progressive enhancement', async ( { page, baseURL } ) => {
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

	await page.goto( '/wp-admin/tools.php?page=mandate-app-security', { waitUntil: 'load' } );
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

	await expect( page.locator( 'input[name="capability_grouping_mode"][value="area"]' ) ).toBeChecked();
	await expect( page.locator( 'input[name="capability_grouping_mode"][value="action"]' ) ).not.toBeChecked();
	await expect( page.locator( '[data-wpm-capability-groups]' ) ).toHaveAttribute( 'data-wpm-capability-source', 'wordpress' );
	await expect( page.locator( '[data-wpm-capability-source-tab][data-wpm-capability-source="wordpress"]' ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( page.locator( '[data-wpm-capability-source-panel][data-wpm-capability-source="third_party"]' ) ).toBeHidden();
	await expect( page.locator( 'input[name="allowed_caps[]"][value="upload_files"]' ) ).toHaveCount( 1 );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();

	const wordpressPanel = page.locator( '[data-wpm-capability-panel="wordpress"]' );
	await expect( wordpressPanel.locator( '.mandate-capability-toolbar' ) ).toBeVisible();
	await expect( wordpressPanel.locator( '.mandate-capability-toolbar h3' ) ).toHaveCount( 0 );
	await expect( wordpressPanel.locator( '[data-wpm-capability-section-index]' ) ).toBeVisible();
	const scrollRegion = wordpressPanel.locator( '.mandate-capability-scroll' );
	const actionRow = wordpressPanel.locator( '.mandate-panel-actions' );
	const indexLinkStyle = await wordpressPanel.locator( '[data-wpm-capability-section-index] a' ).first().evaluate( ( link ) => {
		const style = getComputedStyle( link );
		return {
			backgroundColor: style.backgroundColor,
			borderTopWidth: style.borderTopWidth,
			borderRightWidth: style.borderRightWidth,
			borderBottomWidth: style.borderBottomWidth,
			borderLeftWidth: style.borderLeftWidth,
		};
	} );
	expect( indexLinkStyle ).toEqual( {
		backgroundColor: 'rgba(0, 0, 0, 0)',
		borderTopWidth: '0px',
		borderRightWidth: '0px',
		borderBottomWidth: '0px',
		borderLeftWidth: '0px',
	} );
	const postsSection = capabilitySection( page, 'wordpress', 'area', 'posts' );
	const pagesSection = capabilitySection( page, 'wordpress', 'area', 'pages' );
	const postsRect = await locatorRect( postsSection );
	const pagesRect = await locatorRect( pagesSection );
	expect( Math.abs( postsRect.left - pagesRect.left ) ).toBeLessThanOrEqual( 2 );
	expect( pagesRect.top ).toBeGreaterThanOrEqual( postsRect.bottom );
	const sectionHeadingSpan = await postsSection.evaluate( ( section ) => {
		const legendElement = section.querySelector( 'legend' );
		const legend = legendElement.getBoundingClientRect();
		const title = legendElement.querySelector( 'span:first-child' ).getBoundingClientRect();
		const count = legendElement.querySelector( '.mandate-capability-section-count' ).getBoundingClientRect();
		const scroll = section.closest( '.mandate-capability-scroll' ).getBoundingClientRect();
		const sectionStyle = getComputedStyle( section );
		const legendStyle = getComputedStyle( legendElement );
		const countStyle = getComputedStyle( legendElement.querySelector( '.mandate-capability-section-count' ) );
		return {
			leftOffset: Math.round( Math.abs( legend.left - scroll.left ) ),
			widthOffset: Math.round( Math.abs( legend.width - scroll.width ) ),
			titleCountGap: Math.round( count.left - title.right ),
			sectionBorderBottomWidth: sectionStyle.borderBottomWidth,
			legendBackgroundColor: legendStyle.backgroundColor,
			legendBorderTopWidth: legendStyle.borderTopWidth,
			legendBorderRightWidth: legendStyle.borderRightWidth,
			legendBorderLeftWidth: legendStyle.borderLeftWidth,
			countBackgroundColor: countStyle.backgroundColor,
			countBorderTopWidth: countStyle.borderTopWidth,
		};
	} );
	expect( sectionHeadingSpan.leftOffset ).toBeLessThanOrEqual( 2 );
	expect( sectionHeadingSpan.widthOffset ).toBeLessThanOrEqual( 2 );
	expect( sectionHeadingSpan.titleCountGap ).toBeGreaterThanOrEqual( 0 );
	expect( sectionHeadingSpan.titleCountGap ).toBeLessThanOrEqual( 12 );
	expect( sectionHeadingSpan.sectionBorderBottomWidth ).toBe( '0px' );
	expect( sectionHeadingSpan.legendBackgroundColor ).toBe( 'rgba(0, 0, 0, 0)' );
	expect( sectionHeadingSpan.legendBorderTopWidth ).toBe( '0px' );
	expect( sectionHeadingSpan.legendBorderRightWidth ).toBe( '0px' );
	expect( sectionHeadingSpan.legendBorderLeftWidth ).toBe( '0px' );
	expect( sectionHeadingSpan.countBackgroundColor ).toBe( 'rgba(0, 0, 0, 0)' );
	expect( sectionHeadingSpan.countBorderTopWidth ).toBe( '0px' );
	expect( await firstRowCapabilityColumns( postsSection ) ).toHaveLength( 3 );
	await page.setViewportSize( { width: 760, height: 900 } );
	expect( await firstRowCapabilityColumns( postsSection ) ).toHaveLength( 1 );
	await page.setViewportSize( { width: 1280, height: 900 } );
	const scrollRect = await locatorRect( scrollRegion );
	const actionRect = await locatorRect( actionRow );
	expect( actionRect.top ).toBeGreaterThanOrEqual( scrollRect.bottom );
	expect( Math.abs( actionRect.right - scrollRect.right ) ).toBeLessThanOrEqual( 2 );
	await scrollRegion.evaluate( ( scroll ) => {
		scroll.scrollTop = scroll.scrollHeight;
		const originalScrollTo = scroll.scrollTo.bind( scroll );
		scroll.scrollTo = ( options ) => {
			scroll.dataset.wpmLastScrollBehavior = typeof options === 'object' ? options.behavior : '';
			originalScrollTo( options );
		};
	} );
	await expect( wordpressPanel.locator( '[data-wpm-capability-section-index]' ) ).toBeVisible();
	await wordpressPanel.locator( '[data-wpm-capability-section-target="mandate-wordpress-area-posts-capabilities"]' ).click();
	await expect( scrollRegion ).toHaveAttribute( 'data-wpm-last-scroll-behavior', 'smooth' );
	await expect.poll( async () => postsSection.evaluate( ( section ) => {
		const scroll = section.closest( '.mandate-capability-scroll' ).getBoundingClientRect();
		const rect = section.getBoundingClientRect();
		return Math.round( Math.abs( rect.top - scroll.top ) );
	} ) ).toBeLessThanOrEqual( 20 );
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await scrollRegion.evaluate( ( scroll ) => {
		scroll.scrollTop = scroll.scrollHeight;
		scroll.dataset.wpmLastScrollBehavior = '';
	} );
	await wordpressPanel.locator( '[data-wpm-capability-section-target="mandate-wordpress-area-posts-capabilities"]' ).click();
	await expect( scrollRegion ).toHaveAttribute( 'data-wpm-last-scroll-behavior', 'auto' );
	await page.emulateMedia( { reducedMotion: 'no-preference' } );

	await selectGroupingMode( page, 'action' );
	await expect( page.locator( '[data-wpm-capability-groups]' ) ).toHaveAttribute( 'data-wpm-capability-mode', 'action' );
	await expect( wordpressPanel.locator( '[data-wpm-capability-section-target="mandate-wordpress-action-read-capabilities"]' ) ).toBeVisible();
	await expect( wordpressPanel.locator( '[data-wpm-capability-section-target="mandate-wordpress-area-posts-capabilities"]' ) ).toHaveCount( 0 );
	await expect( page.locator( 'input[name="allowed_caps[]"][value="upload_files"]' ) ).toHaveCount( 1 );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).uncheck();
	await selectGroupingMode( page, 'area' );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await selectCapabilitySource( page, 'third_party' );
	await expect( page.locator( '[data-wpm-capability-groups]' ) ).toHaveAttribute( 'data-wpm-capability-source', 'third_party' );
	await expect( page.locator( '[data-wpm-capability-source-tab][data-wpm-capability-source="third_party"]' ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).toBeChecked();
	await expect( page.locator( 'input[name="allowed_caps[]"][value="upload_files"]' ) ).toHaveCount( 1 );
	await selectCapabilitySource( page, 'wordpress' );
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).check();
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

	expect( new Set( await primitiveCapabilityValues( page, 'wordpress' ) ) ).toEqual( new Set( [ 'read', 'edit_posts', 'upload_files' ] ) );
	expect( new Set( await primitiveCapabilityValues( page, 'third_party' ) ) ).toEqual( new Set( [ 'wpm_manage_widget' ] ) );

	await expect( anyPrimitiveCapInput( page, primary.direct_cap ) ).toHaveCount( 0 );
	await expect( anyPrimitiveCapInput( page, fixture.unassigned_role_cap ) ).toHaveCount( 0 );

	const uploadFilesName = primitiveCapTooltipTarget( page, 'wordpress', 'upload_files' );
	await expect( uploadFilesName ).toHaveAttribute( 'data-wpm-tooltip', '' );
	await expect( primitiveCapTooltipTarget( page, 'third_party', 'wpm_manage_widget' ) ).toHaveCount( 0 );

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

	await page.locator( '[data-wpm-capability-panel="wordpress"] [data-wpm-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).toBeChecked();

	await page.locator( '[data-wpm-capability-panel="wordpress"] [data-wpm-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();

	await selectCapabilitySource( page, 'third_party' );
	await page.locator( '[data-wpm-capability-panel="third_party"] [data-wpm-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();
	await page.locator( '[data-wpm-capability-panel="third_party"] [data-wpm-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).toBeChecked();

	await selectCapabilitySource( page, 'wordpress' );
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
	await page.goto( `/wp-admin/tools.php?page=mandate-app-security&user_id=${primary.user_id}&app_password_uuid=${secondaryPassword.uuid}`, { waitUntil: 'load' } );
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

test( 'admin can lock a scope and the owner cannot edit it from UI or forged POST', async ( { page } ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );
	await loginAsAdmin( page );

	const fixtureResponse = await page.request.get( '/wp-json/mandate-test/v1/fixture' );
	expect( fixtureResponse.ok() ).toBeTruthy();
	const fixture = await fixtureResponse.json();
	const primary = fixture.primary;
	const otherUser = fixture.secondary_user;
	const primaryPassword = primary.passwords.primary;

	await page.goto( '/wp-admin/tools.php?page=mandate-app-security', { waitUntil: 'load' } );
	await selectOptionAndWait( page, page.locator( '#mandate-user' ), primary.user_id );
	await ensureSelectedOption( page, page.locator( '#mandate-password' ), primaryPassword.uuid );
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).uncheck();
	await page.locator( 'input[name="admin_locked"]' ).check();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="mandate_action"][value="save_scope"]' ).click(),
	] );
	await expect( page.locator( '#mandate-scope-form' ) ).toHaveAttribute( 'data-wpm-admin-lock-status', 'locked' );
	await expect( page.locator( 'input[name="admin_locked"]' ) ).toBeChecked();

	await loginAsFixtureUser( page, primary.user_login );
	await page.goto(
		`/wp-admin/tools.php?page=mandate-app-security&user_id=${otherUser.user_id}&app_password_uuid=${primaryPassword.uuid}`,
		{ waitUntil: 'load' }
	);

	await expect( page.locator( '#mandate-user' ) ).toBeDisabled();
	await expect( page.locator( '#mandate-user' ) ).toHaveValue( String( primary.user_id ) );
	await expect( page.locator( '#mandate-scope-form' ) ).toHaveAttribute( 'data-wpm-admin-lock-status', 'locked' );
	await expect( page.getByText( 'This application password scope is locked by an administrator.' ) ).toBeVisible();
	await expect( page.locator( 'input[name="admin_locked"]' ) ).toHaveCount( 0 );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeDisabled();
	await expect( page.locator( '[data-wpm-expiration-input]' ) ).toBeDisabled();
	await expect( page.locator( '[data-wpm-capability-panel="wordpress"] [data-wpm-select-state="unchecked"]' ) ).toBeDisabled();
	await expect( page.locator( 'button[name="mandate_action"][value="save_scope"]' ) ).toBeDisabled();
	await expect( page.locator( 'button[name="mandate_action"][value="clear_scope"]' ) ).toBeDisabled();

	const forged = await page.locator( '#mandate-scope-form' ).evaluate( async ( form ) => {
		const params = new URLSearchParams();
		new FormData( form ).forEach( ( value, key ) => params.append( key, value ) );
		params.set( 'mandate_action', 'save_scope' );
		params.append( 'allowed_caps[]', 'read' );
		params.append( 'allowed_caps[]', 'edit_posts' );
		params.append( 'allowed_caps[]', 'upload_files' );

		const response = await fetch( form.action, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: params.toString(),
			redirect: 'follow',
		} );

		return {
			status: response.status,
			url: response.url,
		};
	} );
	expect( forged.status ).toBe( 200 );
	expect( new URL( forged.url ).searchParams.get( 'mandate_message' ) ).toBe( 'locked' );

	await page.reload( { waitUntil: 'load' } );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( page.locator( '#mandate-scope-form' ) ).toHaveAttribute( 'data-wpm-admin-lock-status', 'locked' );
} );
