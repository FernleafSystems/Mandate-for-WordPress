const base = require( '@playwright/test' );
const { expect, request } = base;

function laneMap() {
	const rawMap = process.env.WPM_BROWSER_LANE_MAP;
	if ( rawMap ) {
		return JSON.parse( rawMap );
	}

	return {
		0: {
			laneIndex: 1,
			baseUrl: process.env.WPM_BROWSER_BASE_URL || 'http://127.0.0.1:8898',
			outputDir: process.env.WPM_BROWSER_OUTPUT_DIR || './test-results/playwright/lane-1/artifacts',
			htmlReportDir: process.env.PLAYWRIGHT_HTML_OUTPUT_DIR || './test-results/playwright/lane-1/html-report',
		},
	};
}

function laneForParallelIndex( parallelIndex ) {
	const lane = laneMap()[ String( parallelIndex ) ];
	if ( !lane || typeof lane !== 'object' ) {
		throw new Error( `No Mandate browser lane configured for parallel index ${parallelIndex}.` );
	}
	if ( !lane.baseUrl ) {
		throw new Error( `Mandate browser lane ${parallelIndex} is missing baseUrl.` );
	}
	if ( !lane.outputDir ) {
		throw new Error( `Mandate browser lane ${parallelIndex} is missing outputDir.` );
	}
	if ( !lane.htmlReportDir ) {
		throw new Error( `Mandate browser lane ${parallelIndex} is missing htmlReportDir.` );
	}

	return {
		laneIndex: Number( lane.laneIndex || parallelIndex + 1 ),
		baseUrl: String( lane.baseUrl ),
		outputDir: String( lane.outputDir ),
		htmlReportDir: String( lane.htmlReportDir ),
	};
}

const test = base.test.extend( {
	lane: [
		async ( {}, use, workerInfo ) => {
			await use( laneForParallelIndex( workerInfo.parallelIndex ) );
		},
		{ scope: 'worker' },
	],
	context: async ( { browser, lane }, use ) => {
		const context = await browser.newContext( {
			baseURL: lane.baseUrl,
		} );
		await use( context );
		await context.close();
	},
	page: async ( { context }, use ) => {
		const page = await context.newPage();
		await use( page );
		await page.close();
	},
	fixture: async ( { page }, use ) => {
		const fixtureResponse = await page.request.get( '/wp-json/mandate-test/v1/fixture' );
		expect( fixtureResponse.ok() ).toBeTruthy();
		await use( await fixtureResponse.json() );
	},
} );

async function loginAsAdmin( page ) {
	await loginAs( page, process.env.WPM_BROWSER_ADMIN_USER || 'admin', process.env.WPM_BROWSER_ADMIN_PASSWORD || 'password' );
}

async function loginAs( page, username, password ) {
	await page.goto( '/wp-admin/', { waitUntil: 'load' } );
	const loginForm = page.locator( '#loginform' );
	if ( await loginForm.isVisible().catch( () => false ) ) {
		await Promise.all( [
			page.waitForURL( /\/wp-admin\//, { waitUntil: 'domcontentloaded' } ),
			loginForm.evaluate(
				( form, credentials ) => {
					form.querySelector( '#user_login' ).value = credentials.username;
					form.querySelector( '#user_pass' ).value = credentials.password;
					form.requestSubmit();
				},
				{ username, password }
			),
		] );
	}
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
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
				const status = form.querySelector( '[data-mdpsc-selection-status]' );
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
	return page.locator( `[data-mdpsc-capability-source-panel][data-mdpsc-capability-source="${source}"] input[name="allowed_caps[]"][value="${capability}"]` );
}

function capabilitySection( page, source, mode, section ) {
	return page.locator( `#mdpsc-${source}-${mode}-${section}-capabilities` );
}

function capabilityItem( page, source, capability ) {
	return page.locator( `[data-mdpsc-capability-source-panel][data-mdpsc-capability-source="${source}"] [data-mdpsc-capability-item][data-mdpsc-capability-name="${capability}"]` );
}

function primitiveCapSlug( page, source, capability ) {
	return capabilityItem( page, source, capability ).locator( 'code' );
}

function primitiveCapInfoTarget( page, source, capability ) {
	return capabilityItem( page, source, capability ).locator( '.mdpsc-capability-info' );
}

function capabilityActionBadge( page, source, capability ) {
	return capabilityItem( page, source, capability ).locator( '.mdpsc-capability-action-badge' );
}

function sectionBulkButton( page, source, mode, section, state ) {
	return capabilitySection( page, source, mode, section ).locator( `[data-mdpsc-select-section][data-mdpsc-select-state="${state}"]` );
}

function anyPrimitiveCapInput( page, capability ) {
	return page.locator( `input[name="allowed_caps[]"][value="${capability}"]` );
}

async function primitiveCapabilityValues( page, source ) {
	return page.locator( `[data-mdpsc-capability-source-panel][data-mdpsc-capability-source="${source}"] input[name="allowed_caps[]"]` )
		.evaluateAll( ( inputs ) => inputs.map( ( input ) => input.value ) );
}

async function selectGroupingMode( page, mode ) {
	await page.locator( `input[name="capability_grouping_mode"][value="${mode}"]` ).check();
}

async function selectCapabilitySource( page, source ) {
	await page.locator( `[data-mdpsc-capability-source-tab][data-mdpsc-capability-source="${source}"]` ).click();
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
	return section.locator( '.mdpsc-capability-list [data-mdpsc-capability-item]' ).evaluateAll( ( items ) => {
		const positions = items.map( ( item ) => {
			const rect = item.getBoundingClientRect();
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

async function sectionHeadingLayout( section ) {
	return section.evaluate( ( sectionElement ) => {
		const legendElement = sectionElement.querySelector( 'legend' );
		const legend = legendElement.getBoundingClientRect();
		const title = legendElement.querySelector( '.mdpsc-capability-section-title span:first-child' ).getBoundingClientRect();
		const count = legendElement.querySelector( '.mdpsc-capability-section-count' ).getBoundingClientRect();
		const actions = legendElement.querySelector( '.mdpsc-capability-section-actions' ).getBoundingClientRect();
		const selectAll = legendElement.querySelector( '[data-mdpsc-select-section][data-mdpsc-select-state="checked"]' ).getBoundingClientRect();
		const separator = legendElement.querySelector( '.mdpsc-capability-section-action-separator' ).getBoundingClientRect();
		const deselectAll = legendElement.querySelector( '[data-mdpsc-select-section][data-mdpsc-select-state="unchecked"]' ).getBoundingClientRect();
		const scroll = sectionElement.closest( '.mdpsc-capability-scroll' ).getBoundingClientRect();
		const sectionStyle = getComputedStyle( sectionElement );
		const legendStyle = getComputedStyle( legendElement );
		const countStyle = getComputedStyle( legendElement.querySelector( '.mdpsc-capability-section-count' ) );
		return {
			leftOffset: Math.round( Math.abs( legend.left - scroll.left ) ),
			widthOffset: Math.round( Math.abs( legend.width - scroll.width ) ),
			titleCountGap: Math.round( count.left - title.right ),
			actionsStartGap: Math.round( actions.left - count.right ),
			selectSeparatorGap: Math.round( separator.left - selectAll.right ),
			separatorDeselectGap: Math.round( deselectAll.left - separator.right ),
			separatorText: legendElement.querySelector( '.mdpsc-capability-section-action-separator' ).textContent.trim(),
			sectionBorderBottomWidth: sectionStyle.borderBottomWidth,
			legendBackgroundColor: legendStyle.backgroundColor,
			legendBorderTopWidth: legendStyle.borderTopWidth,
			legendBorderRightWidth: legendStyle.borderRightWidth,
			legendBorderLeftWidth: legendStyle.borderLeftWidth,
			countBackgroundColor: countStyle.backgroundColor,
			countBorderTopWidth: countStyle.borderTopWidth,
		};
	} );
}

function expectLeftAlignedSectionActions( layout ) {
	expect( layout.leftOffset ).toBeLessThanOrEqual( 2 );
	expect( layout.widthOffset ).toBeLessThanOrEqual( 2 );
	expect( layout.titleCountGap ).toBeGreaterThanOrEqual( 0 );
	expect( layout.titleCountGap ).toBeLessThanOrEqual( 12 );
	expect( layout.actionsStartGap ).toBeGreaterThanOrEqual( 0 );
	expect( layout.actionsStartGap ).toBeLessThanOrEqual( 18 );
	expect( layout.selectSeparatorGap ).toBeGreaterThanOrEqual( 0 );
	expect( layout.selectSeparatorGap ).toBeLessThanOrEqual( 12 );
	expect( layout.separatorDeselectGap ).toBeGreaterThanOrEqual( 0 );
	expect( layout.separatorDeselectGap ).toBeLessThanOrEqual( 12 );
	expect( layout.separatorText ).toBe( '/' );
	expect( layout.sectionBorderBottomWidth ).toBe( '0px' );
	expect( layout.legendBackgroundColor ).toBe( 'rgba(0, 0, 0, 0)' );
	expect( layout.legendBorderTopWidth ).toBe( '0px' );
	expect( layout.legendBorderRightWidth ).toBe( '0px' );
	expect( layout.legendBorderLeftWidth ).toBe( '0px' );
	expect( layout.countBackgroundColor ).toBe( 'rgba(0, 0, 0, 0)' );
	expect( layout.countBorderTopWidth ).toBe( '0px' );
}

async function capabilityControlLayout( page, source, capability ) {
	return capabilityItem( page, source, capability ).evaluate( ( item ) => {
		const rect = ( element ) => {
			const bounds = element.getBoundingClientRect();
			return {
				left: Math.round( bounds.left ),
				right: Math.round( bounds.right ),
				width: Math.round( bounds.width ),
			};
		};
		return {
			input: rect( item.querySelector( 'input[type="checkbox"]' ) ),
			code: rect( item.querySelector( '.mdpsc-capability-name code' ) ),
			badge: rect( item.querySelector( '.mdpsc-capability-action-badge' ) ),
			info: rect( item.querySelector( '.mdpsc-capability-info, .mdpsc-capability-info-space' ) ),
			badgeDisplay: getComputedStyle( item.querySelector( '.mdpsc-capability-action-badge' ) ).display,
		};
	} );
}

function expectAreaControlLayout( layout ) {
	expect( layout.badgeDisplay ).not.toBe( 'none' );
	expect( layout.input.right ).toBeLessThanOrEqual( layout.code.left );
	expect( layout.code.right ).toBeLessThanOrEqual( layout.badge.left );
	expect( layout.badge.right ).toBeLessThanOrEqual( layout.info.left );
	expect( layout.badge.left - layout.code.right ).toBeGreaterThanOrEqual( 0 );
	expect( layout.badge.left - layout.code.right ).toBeLessThanOrEqual( 16 );
	expect( layout.info.left - layout.badge.right ).toBeGreaterThanOrEqual( 0 );
	expect( layout.info.left - layout.badge.right ).toBeLessThanOrEqual( 16 );
}

function expectActionControlLayout( layout ) {
	expect( layout.badgeDisplay ).toBe( 'none' );
	expect( layout.input.right ).toBeLessThanOrEqual( layout.code.left );
	expect( layout.code.right ).toBeLessThanOrEqual( layout.info.left );
	expect( layout.info.left - layout.code.right ).toBeGreaterThanOrEqual( 0 );
	expect( layout.info.left - layout.code.right ).toBeLessThanOrEqual( 16 );
}

test( 'admin can manage grouped application password scopes with progressive enhancement', async ( { page, lane, fixture } ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );
	await loginAsAdmin( page );

	const primary = fixture.primary;
	const otherUser = fixture.secondary_user;
	const primaryPassword = primary.passwords.primary;
	const secondaryPassword = primary.passwords.secondary;
	const otherPassword = otherUser.passwords.primary;

	await page.goto( '/wp-admin/tools.php?page=mandate-app-security', { waitUntil: 'load' } );
	const userSelect = page.locator( '#mdpsc-user' );
	await expect( userSelect ).toBeVisible();

	await selectOptionAndWait( page, userSelect, primary.user_id );
	await ensureSelectedOption( page, page.locator( '#mdpsc-password' ), primaryPassword.uuid );
	await expect( page.locator( '#mdpsc-password' ) ).toHaveValue( primaryPassword.uuid );

	await selectOptionWithDelayedNavigation(
		page,
		page.locator( '#mdpsc-password' ),
		secondaryPassword.uuid,
		`app_password_uuid=${secondaryPassword.uuid}`
	);
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBe( secondaryPassword.uuid );
	await expect( page.locator( '#mdpsc-password' ) ).toHaveValue( secondaryPassword.uuid );

	await selectOptionAndWait( page, page.locator( '#mdpsc-user' ), otherUser.user_id );
	expect( new URL( page.url() ).searchParams.get( 'app_password_uuid' ) ).toBeNull();
	await expect( page.locator( '#mdpsc-password' ) ).toHaveValue( otherPassword.uuid );
	await expect( page.locator( '#mdpsc-scope-summary [data-mdpsc-admin-lock-input]' ) ).toBeDisabled();

	await selectOptionAndWait( page, page.locator( '#mdpsc-user' ), primary.user_id );
	await ensureSelectedOption( page, page.locator( '#mdpsc-password' ), primaryPassword.uuid );
	await expect( page.locator( '#mdpsc-password' ) ).toHaveValue( primaryPassword.uuid );
	const selectionColumns = await page.locator( '.mdpsc-selection-grid > .mdpsc-selection-column' )
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
	const selectionTitleTops = await page.locator( '.mdpsc-selection-grid' ).evaluate( ( grid ) => (
		Array.from( grid.querySelectorAll( '.mdpsc-field-title' ) )
			.map( ( title ) => Math.round( title.getBoundingClientRect().top ) )
	) );
	expect( selectionTitleTops ).toHaveLength( 3 );
	expect( Math.abs( selectionTitleTops[ 0 ] - selectionTitleTops[ 1 ] ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( selectionTitleTops[ 1 ] - selectionTitleTops[ 2 ] ) ).toBeLessThanOrEqual( 2 );
	await expect( page.locator( '#mdpsc-scope-summary #mdpsc-scope-summary-title' ) ).toHaveCount( 0 );
	const passwordInfoPlacement = await page.locator( '#mdpsc-password' ).evaluate( ( passwordSelect ) => {
		const passwordInfo = document.querySelector( '#mdpsc-password-info' );
		const scopeSummary = document.querySelector( '#mdpsc-scope-summary' );
		const passwordRect = passwordSelect.getBoundingClientRect();
		const infoRect = passwordInfo.getBoundingClientRect();
		const scopeRect = scopeSummary.getBoundingClientRect();

		return {
			infoLeft: Math.round( infoRect.left ),
			infoTop: Math.round( infoRect.top ),
			passwordBottom: Math.round( passwordRect.bottom ),
			passwordLeft: Math.round( passwordRect.left ),
			scopeLeft: Math.round( scopeRect.left ),
			scopeTop: Math.round( scopeRect.top ),
		};
	} );
	expect( passwordInfoPlacement.infoTop ).toBeGreaterThan( passwordInfoPlacement.passwordBottom );
	expect( Math.abs( passwordInfoPlacement.infoLeft - passwordInfoPlacement.passwordLeft ) ).toBeLessThanOrEqual( 2 );
	expect( passwordInfoPlacement.scopeLeft ).toBeGreaterThan( passwordInfoPlacement.infoLeft );
	expect( passwordInfoPlacement.scopeTop ).toBeLessThan( passwordInfoPlacement.infoTop );
	const summaryCardStyles = await page.locator( '#mdpsc-role-summary' ).evaluate( ( roleSummary ) => {
		const summaryCards = [
			document.querySelector( '#mdpsc-password-info' ),
			document.querySelector( '#mdpsc-scope-summary' ),
		];
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

		return summaryCards.flatMap( ( summaryCard ) => properties.map( ( property ) => [
			getComputedStyle( roleSummary )[ property ],
			getComputedStyle( summaryCard )[ property ],
		] ) );
	} );
	summaryCardStyles.forEach( ( [ roleValue, passwordValue ] ) => {
		expect( roleValue ).toBe( passwordValue );
	} );
	await page.setViewportSize( { width: 760, height: 900 } );
	const mobileSelectionColumns = await page.locator( '.mdpsc-selection-grid > .mdpsc-selection-column' )
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
	await expect( page.locator( '[data-mdpsc-capability-groups]' ) ).toHaveAttribute( 'data-mdpsc-capability-source', 'wordpress' );
	await expect( page.locator( '[data-mdpsc-capability-source-tab][data-mdpsc-capability-source="wordpress"]' ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( page.locator( '[data-mdpsc-capability-source-panel][data-mdpsc-capability-source="third_party"]' ) ).toBeHidden();
	await expect( page.locator( 'input[name="allowed_caps[]"][value="upload_files"]' ) ).toHaveCount( 1 );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();

	const wordpressPanel = page.locator( '[data-mdpsc-capability-panel="wordpress"]' );
	await expect( wordpressPanel.locator( '.mdpsc-capability-toolbar' ) ).toBeVisible();
	await expect( wordpressPanel.locator( '.mdpsc-capability-toolbar h3' ) ).toHaveCount( 0 );
	await expect( wordpressPanel.locator( '[data-mdpsc-capability-section-index]' ) ).toBeVisible();
	const scrollRegion = wordpressPanel.locator( '.mdpsc-capability-scroll' );
	const actionRow = wordpressPanel.locator( '.mdpsc-panel-actions' );
	const indexLinkStyle = await wordpressPanel.locator( '[data-mdpsc-capability-section-index] a' ).first().evaluate( ( link ) => {
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
	expectLeftAlignedSectionActions( await sectionHeadingLayout( postsSection ) );
	await expect( sectionBulkButton( page, 'wordpress', 'area', 'posts', 'checked' ) ).toBeVisible();
	await expect( sectionBulkButton( page, 'wordpress', 'area', 'posts', 'unchecked' ) ).toBeVisible();
	const postsItemOrder = await postsSection.locator( '[data-mdpsc-capability-item]' ).evaluateAll( ( items ) => items.map( ( item ) => item.dataset.mdpscCapabilityName ) );
	expect( postsItemOrder ).toEqual( [ 'read_post', 'edit_post', 'edit_posts', 'delete_post' ] );
	const postsActionBadges = await postsSection.locator( '.mdpsc-capability-action-badge' ).evaluateAll( ( badges ) => badges.map( ( badge ) => badge.textContent.trim() ) );
	expect( postsActionBadges ).toEqual( [ 'R', 'W', 'W', 'D' ] );
	expectAreaControlLayout( await capabilityControlLayout( page, 'wordpress', 'upload_files' ) );
	expect( await firstRowCapabilityColumns( postsSection ) ).toHaveLength( 3 );
	await page.setViewportSize( { width: 760, height: 900 } );
	expectAreaControlLayout( await capabilityControlLayout( page, 'wordpress', 'upload_files' ) );
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
			scroll.dataset.mdpscLastScrollBehavior = typeof options === 'object' ? options.behavior : '';
			originalScrollTo( options );
		};
	} );
	await expect( wordpressPanel.locator( '[data-mdpsc-capability-section-index]' ) ).toBeVisible();
	await wordpressPanel.locator( '[data-mdpsc-capability-section-target="mdpsc-wordpress-area-posts-capabilities"]' ).click();
	await expect( scrollRegion ).toHaveAttribute( 'data-mdpsc-last-scroll-behavior', 'smooth' );
	await expect.poll( async () => postsSection.evaluate( ( section ) => {
		const scroll = section.closest( '.mdpsc-capability-scroll' ).getBoundingClientRect();
		const rect = section.getBoundingClientRect();
		return Math.round( Math.abs( rect.top - scroll.top ) );
	} ) ).toBeLessThanOrEqual( 20 );
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await scrollRegion.evaluate( ( scroll ) => {
		scroll.scrollTop = scroll.scrollHeight;
		scroll.dataset.mdpscLastScrollBehavior = '';
	} );
	await wordpressPanel.locator( '[data-mdpsc-capability-section-target="mdpsc-wordpress-area-posts-capabilities"]' ).click();
	await expect( scrollRegion ).toHaveAttribute( 'data-mdpsc-last-scroll-behavior', 'auto' );
	await page.emulateMedia( { reducedMotion: 'no-preference' } );

	await selectGroupingMode( page, 'action' );
	await expect( page.locator( '[data-mdpsc-capability-groups]' ) ).toHaveAttribute( 'data-mdpsc-capability-mode', 'action' );
	await expect( wordpressPanel.locator( '[data-mdpsc-capability-section-target="mdpsc-wordpress-action-read-capabilities"]' ) ).toBeVisible();
	expectLeftAlignedSectionActions( await sectionHeadingLayout( capabilitySection( page, 'wordpress', 'action', 'read' ) ) );
	await expect( wordpressPanel.locator( '[data-mdpsc-capability-section-target="mdpsc-wordpress-area-posts-capabilities"]' ) ).toHaveCount( 0 );
	await expect( capabilityActionBadge( page, 'wordpress', 'upload_files' ) ).toBeHidden();
	expectActionControlLayout( await capabilityControlLayout( page, 'wordpress', 'upload_files' ) );
	await expect( page.locator( 'input[name="allowed_caps[]"][value="upload_files"]' ) ).toHaveCount( 1 );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).uncheck();
	await selectGroupingMode( page, 'area' );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await selectCapabilitySource( page, 'third_party' );
	await expect( page.locator( '[data-mdpsc-capability-groups]' ) ).toHaveAttribute( 'data-mdpsc-capability-source', 'third_party' );
	await expect( page.locator( '[data-mdpsc-capability-source-tab][data-mdpsc-capability-source="third_party"]' ) ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).toBeChecked();
	await expect( page.locator( 'input[name="allowed_caps[]"][value="upload_files"]' ) ).toHaveCount( 1 );
	await selectCapabilitySource( page, 'wordpress' );
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).check();
	await expect( page.locator( '#mdpsc-scope-summary [data-mdpsc-expiration-input]' ) ).toHaveCount( 1 );
	await expect( page.locator( '#mdpsc-scope-form [data-mdpsc-expiration-input]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toHaveValue( '' );
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toBeHidden();
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toBeVisible();
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toHaveAttribute( 'data-mdpsc-expiration-state', 'never' );
	await page.locator( '[data-mdpsc-expiration-summary]' ).click();
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toBeHidden();
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toBeVisible();
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toBeFocused();

	await page.locator( '[data-mdpsc-expiration-input]' ).fill( fixture.expiration_dates.future );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="mdpsc_action"][value="save_scope"]' ).click(),
	] );
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toHaveValue( fixture.expiration_dates.future );
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toBeHidden();
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toBeVisible();
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toHaveAttribute( 'data-mdpsc-expiration-state', 'date' );

	expect( new Set( await primitiveCapabilityValues( page, 'wordpress' ) ) ).toEqual( new Set( [ 'read', 'edit_posts', 'upload_files' ] ) );
	expect( new Set( await primitiveCapabilityValues( page, 'third_party' ) ) ).toEqual( new Set( [ 'wpm_manage_widget' ] ) );

	await expect( anyPrimitiveCapInput( page, primary.direct_cap ) ).toHaveCount( 0 );
	await expect( anyPrimitiveCapInput( page, fixture.unassigned_role_cap ) ).toHaveCount( 0 );

	const uploadFilesSlug = primitiveCapSlug( page, 'wordpress', 'upload_files' );
	await expect( uploadFilesSlug ).not.toHaveAttribute( 'data-mdpsc-tooltip', '' );
	await uploadFilesSlug.hover();
	await expect( page.locator( '#mdpsc-capability-tooltip' ) ).toHaveCount( 0 );
	const uploadFilesInfo = primitiveCapInfoTarget( page, 'wordpress', 'upload_files' );
	await expect( uploadFilesInfo ).toHaveCount( 1 );
	await uploadFilesInfo.hover();
	await expect( page.locator( '#mdpsc-capability-tooltip' ) ).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await uploadFilesInfo.focus();
	await expect( page.locator( '#mdpsc-capability-tooltip' ) ).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await expect( primitiveCapInfoTarget( page, 'third_party', 'wpm_manage_widget' ) ).toHaveCount( 0 );
	const uploadFilesActionBadge = capabilityActionBadge( page, 'wordpress', 'upload_files' );
	await expect( uploadFilesActionBadge ).toHaveText( 'W' );
	await uploadFilesActionBadge.hover();
	await expect( page.locator( '#mdpsc-capability-tooltip' ) ).toHaveText( 'Write' );
	await page.keyboard.press( 'Escape' );
	await uploadFilesActionBadge.focus();
	await expect( page.locator( '#mdpsc-capability-tooltip' ) ).toHaveText( 'Write' );
	await page.keyboard.press( 'Escape' );

	const scopedRequest = await request.newContext( {
		baseURL: lane.baseUrl,
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

	await sectionBulkButton( page, 'wordpress', 'area', 'posts', 'unchecked' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'edit_posts' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).toBeChecked();

	await sectionBulkButton( page, 'wordpress', 'area', 'posts', 'checked' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'edit_posts' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();

	await page.locator( '[data-mdpsc-capability-panel="wordpress"] [data-mdpsc-select-panel][data-mdpsc-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).toBeChecked();

	await page.locator( '[data-mdpsc-capability-panel="wordpress"] [data-mdpsc-select-panel][data-mdpsc-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();

	await selectCapabilitySource( page, 'third_party' );
	await page.locator( '[data-mdpsc-capability-panel="third_party"] [data-mdpsc-select-panel][data-mdpsc-select-state="unchecked"]' ).click();
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'read' ) ).toBeChecked();
	await page.locator( '[data-mdpsc-capability-panel="third_party"] [data-mdpsc-select-panel][data-mdpsc-select-state="checked"]' ).click();
	await expect( primitiveCapInput( page, 'third_party', 'wpm_manage_widget' ) ).toBeChecked();

	await selectCapabilitySource( page, 'wordpress' );
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).uncheck();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="mdpsc_action"][value="save_scope"]' ).click(),
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
		page.locator( 'button[name="mdpsc_action"][value="clear_scope"]' ).click(),
	] );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeChecked();
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toHaveValue( '' );
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toBeHidden();
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toBeVisible();
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toHaveAttribute( 'data-mdpsc-expiration-state', 'never' );

	capabilityResponse = await scopedRequest.get( '/wp-json/mandate-test/v1/caps' );
	expect( capabilityResponse.ok() ).toBeTruthy();
	capabilities = await capabilityResponse.json();
	expect( capabilities.upload_files ).toBe( true );
	expect( capabilities.delete_posts ).toBe( true );
	expect( capabilities.wpm_manage_widget ).toBe( true );
	await scopedRequest.dispose();

	const secondaryRequest = await request.newContext( {
		baseURL: lane.baseUrl,
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
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toHaveAttribute( 'data-mdpsc-expiration-state', 'expired' );
	await expect( page.locator( '[data-mdpsc-expiration-summary]' ) ).toHaveCSS( 'color', 'rgb(179, 45, 46)' );

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

test( 'admin can lock a scope and the owner cannot edit it from UI or forged POST', async ( { page, fixture } ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );
	await loginAsAdmin( page );

	const primary = fixture.primary;
	const otherUser = fixture.secondary_user;
	const primaryPassword = primary.passwords.primary;

	await page.goto( '/wp-admin/tools.php?page=mandate-app-security', { waitUntil: 'load' } );
	await ensureSelectedOption( page, page.locator( '#mdpsc-user' ), primary.user_id );
	await ensureSelectedOption( page, page.locator( '#mdpsc-password' ), primaryPassword.uuid );
	await primitiveCapInput( page, 'wordpress', 'upload_files' ).uncheck();
	const adminLockInput = page.locator( '#mdpsc-scope-summary [data-mdpsc-admin-lock-input]' );
	await expect( adminLockInput ).toHaveCount( 1 );
	await expect( page.locator( '#mdpsc-scope-form [data-mdpsc-admin-lock-input]' ) ).toHaveCount( 0 );
	await adminLockInput.check();
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'load' } ),
		page.locator( 'button[name="mdpsc_action"][value="save_scope"]' ).click(),
	] );
	await expect( page.locator( '#mdpsc-scope-form' ) ).toHaveAttribute( 'data-mdpsc-admin-lock-status', 'locked' );
	await expect( adminLockInput ).toBeChecked();

	await loginAsFixtureUser( page, primary.user_login );
	await page.goto(
		`/wp-admin/tools.php?page=mandate-app-security&user_id=${otherUser.user_id}&app_password_uuid=${primaryPassword.uuid}`,
		{ waitUntil: 'load' }
	);

	await expect( page.locator( '#mdpsc-user' ) ).toBeDisabled();
	await expect( page.locator( '#mdpsc-user' ) ).toHaveValue( String( primary.user_id ) );
	await expect( page.locator( '#mdpsc-scope-form' ) ).toHaveAttribute( 'data-mdpsc-admin-lock-status', 'locked' );
	await expect( page.locator( '[data-mdpsc-admin-lock-input]' ) ).toHaveCount( 0 );
	await expect( page.locator( 'input[name="admin_locked"]' ) ).toHaveCount( 0 );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).toBeDisabled();
	await expect( page.locator( '[data-mdpsc-expiration-input]' ) ).toBeDisabled();
	await expect( page.locator( '[data-mdpsc-capability-panel="wordpress"] [data-mdpsc-select-panel][data-mdpsc-select-state="unchecked"]' ) ).toBeDisabled();
	await expect( page.locator( '[data-mdpsc-capability-panel="wordpress"] [data-mdpsc-select-section]:disabled' ) ).toHaveCount( 12 );
	await expect( page.locator( 'button[name="mdpsc_action"][value="save_scope"]' ) ).toBeDisabled();
	await expect( page.locator( 'button[name="mdpsc_action"][value="clear_scope"]' ) ).toBeDisabled();

	const forged = await page.locator( '#mdpsc-scope-form' ).evaluate( async ( form ) => {
		const params = new URLSearchParams();
		new FormData( form ).forEach( ( value, key ) => params.append( key, value ) );
		params.set( 'mdpsc_action', 'save_scope' );
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
	expect( new URL( forged.url ).searchParams.get( 'mdpsc_message' ) ).toBe( 'locked' );

	await page.reload( { waitUntil: 'load' } );
	await expect( primitiveCapInput( page, 'wordpress', 'upload_files' ) ).not.toBeChecked();
	await expect( page.locator( '#mdpsc-scope-form' ) ).toHaveAttribute( 'data-mdpsc-admin-lock-status', 'locked' );
} );
