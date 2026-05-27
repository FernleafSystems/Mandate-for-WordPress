import './admin-page.css';

const TOOLTIP_ID = 'mandate-capability-tooltip';

let tooltipElement = null;
let tooltipTarget = null;
let tooltipGlobalEventsBound = false;

function submitForm( form ) {
	if ( typeof form.requestSubmit === 'function' ) {
		form.requestSubmit();
		return;
	}

	HTMLFormElement.prototype.submit.call( form );
}

function setSelectionLoading( form ) {
	form.classList.add( 'is-wpm-loading' );
	form.setAttribute( 'aria-busy', 'true' );

	const status = form.querySelector( '[data-wpm-selection-status]' );
	if ( status ) {
		status.hidden = false;
	}
}

function enhanceSelectionForm( root ) {
	const form = root.querySelector( '[data-wpm-selection-form]' );
	if ( !form ) {
		return;
	}

	const userSelect = form.querySelector( '#mandate-user' );
	const passwordSelect = form.querySelector( '#mandate-password' );

	if ( userSelect ) {
		userSelect.addEventListener( 'change', () => {
			setSelectionLoading( form );
			if ( passwordSelect ) {
				passwordSelect.value = '';
				passwordSelect.disabled = true;
			}
			submitForm( form );
		} );
	}

	if ( passwordSelect ) {
		passwordSelect.addEventListener( 'change', () => {
			setSelectionLoading( form );
			submitForm( form );
		} );
	}
}

function enhanceBulkControls( root ) {
	root.addEventListener( 'click', ( event ) => {
		if ( !( event.target instanceof Element ) ) {
			return;
		}

		const sectionButton = event.target.closest( '[data-wpm-select-section]' );
		if ( sectionButton ) {
			if ( sectionButton.disabled ) {
				return;
			}

			const section = sectionButton.closest( '[data-wpm-capability-section]' );
			if ( !section ) {
				return;
			}

			setCheckedState( section, sectionButton.dataset.wpmSelectState === 'checked' );
			return;
		}

		const panelButton = event.target.closest( '[data-wpm-select-panel]' );
		if ( !panelButton || panelButton.disabled ) {
			return;
		}

		const panel = panelButton.closest( '[data-wpm-capability-panel]' );
		if ( !panel ) {
			return;
		}

		setCheckedState( panel, panelButton.dataset.wpmSelectState === 'checked' );
	} );
}

function setCheckedState( container, checked ) {
	container.querySelectorAll( 'input[type="checkbox"][name="allowed_caps[]"], input[type="checkbox"][name="allowed_meta_caps[]"]' )
		.forEach( ( input ) => {
			input.checked = checked;
		} );
}

function parseGroupingConfig( container ) {
	const configValue = container.dataset.wpmCapabilityGroupingConfig;
	if ( !configValue ) {
		return null;
	}

	try {
		return JSON.parse( configValue );
	} catch ( error ) {
		return null;
	}
}

function createCapabilitySection( sectionConfig, items ) {
	const section = document.createElement( 'fieldset' );
	section.id = sectionConfig.id;
	section.className = 'mandate-capability-section';
	section.dataset.wpmCapabilitySection = '';

	const legend = document.createElement( 'legend' );
	const title = document.createElement( 'span' );
	title.className = 'mandate-capability-section-title';

	const label = document.createElement( 'span' );
	label.textContent = sectionConfig.label;
	title.appendChild( label );

	const count = document.createElement( 'span' );
	count.className = 'mandate-capability-section-count';
	count.textContent = sectionConfig.count;
	title.appendChild( count );
	legend.appendChild( title );

	const actions = document.createElement( 'span' );
	actions.className = 'mandate-capability-section-actions';
	actions.appendChild( createSectionBulkButton( sectionConfig.bulk_actions.select_all ) );
	actions.appendChild( createSectionActionSeparator() );
	actions.appendChild( createSectionBulkButton( sectionConfig.bulk_actions.deselect_all ) );
	legend.appendChild( actions );
	section.appendChild( legend );

	const list = document.createElement( 'div' );
	list.className = 'mandate-capability-list';
	items.forEach( ( item ) => list.appendChild( item ) );
	section.appendChild( list );

	return section;
}

function createSectionActionSeparator() {
	const separator = document.createElement( 'span' );
	separator.className = 'mandate-capability-section-action-separator';
	separator.setAttribute( 'aria-hidden', 'true' );
	separator.textContent = '/';
	return separator;
}

function createSectionBulkButton( actionConfig ) {
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'mandate-link-button';
	button.dataset.wpmSelectState = actionConfig.state;
	button.dataset.wpmSelectSection = '';
	button.disabled = actionConfig.disabled;
	button.textContent = actionConfig.label;
	return button;
}

function createCapabilityIndexLink( sectionConfig ) {
	const link = document.createElement( 'a' );
	link.href = `#${ sectionConfig.id }`;
	link.dataset.wpmCapabilityIndexLink = '';
	link.dataset.wpmCapabilitySectionTarget = sectionConfig.id;

	const label = document.createElement( 'span' );
	label.textContent = sectionConfig.label;
	link.appendChild( label );

	const count = document.createElement( 'span' );
	count.className = 'mandate-capability-section-count';
	count.textContent = sectionConfig.count;
	link.appendChild( count );

	return link;
}

function createEmptyMessage( text ) {
	const message = document.createElement( 'p' );
	message.className = 'description';
	message.textContent = text;
	return message;
}

function sourcePanelFor( container, source ) {
	return container.querySelector( `[data-wpm-capability-source-panel][data-wpm-capability-source="${ source.key }"]` );
}

function capabilityItemMap( container ) {
	const items = new Map();
	container.querySelectorAll( '[data-wpm-capability-item]' ).forEach( ( item ) => {
		items.set( item.dataset.wpmCapabilityKey, item );
	} );
	return items;
}

function sectionItems( sectionConfig, items ) {
	return sectionConfig.itemKeys.map( ( itemKey ) => items.get( itemKey ) );
}

function scrollCapabilitySectionIntoView( target ) {
	const scroll = target.closest( '.mandate-capability-scroll' );
	if ( !scroll ) {
		return;
	}

	const scrollRect = scroll.getBoundingClientRect();
	const targetRect = target.getBoundingClientRect();
	const prefersReducedMotion = window.matchMedia
		&& window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	scroll.scrollTo( {
		top: scroll.scrollTop + targetRect.top - scrollRect.top,
		behavior: prefersReducedMotion ? 'auto' : 'smooth',
	} );
}

function renderCapabilityIndex( panel, modeConfig ) {
	const index = panel.querySelector( '[data-wpm-capability-section-index]' );
	if ( !index ) {
		return;
	}

	const fragment = document.createDocumentFragment();
	modeConfig.sections.forEach( ( sectionConfig ) => {
		fragment.appendChild( createCapabilityIndexLink( sectionConfig ) );
	} );
	index.replaceChildren( fragment );
}

function renderCapabilitySourcePanel( panel, source, mode, items ) {
	const scroll = panel.querySelector( '.mandate-capability-scroll' );
	const modeConfig = source.modes[ mode ];
	if ( !scroll || !modeConfig ) {
		return;
	}

	renderCapabilityIndex( panel, modeConfig );

	const fragment = document.createDocumentFragment();
	modeConfig.sections.forEach( ( sectionConfig ) => {
		fragment.appendChild( createCapabilitySection( sectionConfig, sectionItems( sectionConfig, items ) ) );
	} );

	if ( !fragment.childNodes.length ) {
		fragment.appendChild( createEmptyMessage( source.emptyText ) );
	}

	scroll.replaceChildren( fragment );
}

function renderCapabilityGroups( container, config, mode ) {
	if ( !config.sources || !config.sources.length ) {
		return;
	}

	container.dataset.wpmCapabilityMode = mode;
	const items = capabilityItemMap( container );
	config.sources.forEach( ( source ) => {
		const panel = sourcePanelFor( container, source );
		if ( panel ) {
			renderCapabilitySourcePanel( panel, source, mode, items );
		}
	} );
}

function setActiveCapabilitySource( form, container, sourceKey ) {
	container.dataset.wpmCapabilitySource = sourceKey;

	form.querySelectorAll( '[data-wpm-capability-source-tab]' ).forEach( ( tab ) => {
		const active = tab.dataset.wpmCapabilitySource === sourceKey;
		tab.classList.toggle( 'is-active', active );
		tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		tab.setAttribute( 'tabindex', active ? '0' : '-1' );
	} );

	container.querySelectorAll( '[data-wpm-capability-source-panel]' ).forEach( ( panel ) => {
		panel.hidden = panel.dataset.wpmCapabilitySource !== sourceKey;
	} );
}

function enhanceCapabilityGrouping( root ) {
	const form = root.querySelector( '.mandate-scope-form' );
	if ( !form ) {
		return;
	}

	const container = form.querySelector( '[data-wpm-capability-groups]' );
	const config = container ? parseGroupingConfig( container ) : null;
	const controls = Array.from( form.querySelectorAll( '[data-wpm-capability-grouping-mode]' ) );
	const sourceTabs = Array.from( form.querySelectorAll( '[data-wpm-capability-source-tab]' ) );
	if ( !container || !config || !controls.length || !sourceTabs.length ) {
		return;
	}

	renderCapabilityGroups( container, config, container.dataset.wpmCapabilityMode || config.defaultMode );
	setActiveCapabilitySource( form, container, container.dataset.wpmCapabilitySource || config.defaultSource );

	controls.forEach( ( control ) => {
		control.addEventListener( 'change', () => {
			if ( control.checked ) {
				hideTooltip();
				renderCapabilityGroups( container, config, control.value );
			}
		} );
	} );

	sourceTabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => {
			hideTooltip();
			setActiveCapabilitySource( form, container, tab.dataset.wpmCapabilitySource );
		} );
	} );

	container.addEventListener( 'click', ( event ) => {
		const link = event.target instanceof Element
			? event.target.closest( '[data-wpm-capability-index-link]' )
			: null;
		if ( !link || !container.contains( link ) ) {
			return;
		}

		const targetId = link.dataset.wpmCapabilitySectionTarget;
		const target = targetId ? document.getElementById( targetId ) : null;
		if ( target ) {
			event.preventDefault();
			hideTooltip();
			scrollCapabilitySectionIntoView( target );
		}
	} );
}

function enhanceExpirationSummary( root ) {
	root.querySelectorAll( '[data-wpm-expiration-summary]' ).forEach( ( summary ) => {
		const inputId = summary.getAttribute( 'aria-controls' );
		const input = inputId ? document.getElementById( inputId ) : null;
		if ( !input || !root.contains( input ) ) {
			return;
		}

		summary.hidden = false;
		summary.setAttribute( 'aria-expanded', 'false' );
		input.hidden = true;

		summary.addEventListener( 'click', () => {
			summary.hidden = true;
			summary.setAttribute( 'aria-expanded', 'true' );
			input.hidden = false;
			input.focus();

			if ( typeof input.showPicker === 'function' ) {
				try {
					input.showPicker();
				} catch ( error ) {
					// Some browsers restrict showPicker() to specific activation paths.
				}
			}
		} );
	} );
}

function getTooltipElement() {
	if ( tooltipElement ) {
		return tooltipElement;
	}

	tooltipElement = document.createElement( 'div' );
	tooltipElement.id = TOOLTIP_ID;
	tooltipElement.className = 'mandate-tooltip';
	tooltipElement.setAttribute( 'role', 'tooltip' );
	tooltipElement.hidden = true;
	document.body.appendChild( tooltipElement );

	return tooltipElement;
}

function positionTooltip( target ) {
	const tooltip = getTooltipElement();
	const targetRect = target.getBoundingClientRect();
	const tooltipRect = tooltip.getBoundingClientRect();
	const viewportPadding = 8;
	const gap = 8;
	const viewportWidth = document.documentElement.clientWidth;
	let top = targetRect.top - tooltipRect.height - gap;

	if ( top < viewportPadding ) {
		top = targetRect.bottom + gap;
	}

	const centeredLeft = targetRect.left + ( targetRect.width / 2 ) - ( tooltipRect.width / 2 );
	const maxLeft = Math.max( viewportPadding, viewportWidth - tooltipRect.width - viewportPadding );
	const left = Math.min( Math.max( viewportPadding, centeredLeft ), maxLeft );

	tooltip.style.left = `${left}px`;
	tooltip.style.top = `${top}px`;
}

function hideTooltip() {
	if ( tooltipTarget ) {
		tooltipTarget.removeAttribute( 'aria-describedby' );
		tooltipTarget = null;
	}

	if ( tooltipElement ) {
		tooltipElement.hidden = true;
		tooltipElement.textContent = '';
	}
}

function showTooltip( target ) {
	const text = target.dataset.wpmTooltipText;
	if ( !text ) {
		hideTooltip();
		return;
	}

	const tooltip = getTooltipElement();
	if ( tooltipTarget && tooltipTarget !== target ) {
		tooltipTarget.removeAttribute( 'aria-describedby' );
	}

	tooltipTarget = target;
	tooltip.textContent = text;
	tooltip.hidden = false;
	target.setAttribute( 'aria-describedby', TOOLTIP_ID );
	positionTooltip( target );
}

function bindTooltipGlobalEvents() {
	if ( tooltipGlobalEventsBound ) {
		return;
	}

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			hideTooltip();
		}
	} );
	window.addEventListener( 'scroll', hideTooltip, true );
	window.addEventListener( 'resize', hideTooltip );
	tooltipGlobalEventsBound = true;
}

function closestTooltipTarget( event ) {
	if ( !( event.target instanceof Element ) ) {
		return null;
	}

	return event.target.closest( '[data-wpm-tooltip]' );
}

function enhanceTooltips( root ) {
	bindTooltipGlobalEvents();

	root.addEventListener( 'pointerover', ( event ) => {
		const target = closestTooltipTarget( event );
		if ( target ) {
			showTooltip( target );
		}
	} );

	root.addEventListener( 'pointerout', ( event ) => {
		const target = closestTooltipTarget( event );
		const movedInsideTarget = event.relatedTarget instanceof Node && target && target.contains( event.relatedTarget );
		if ( target && target === tooltipTarget && !movedInsideTarget ) {
			hideTooltip();
		}
	} );

	root.addEventListener( 'focusin', ( event ) => {
		const target = closestTooltipTarget( event );
		if ( target ) {
			showTooltip( target );
		}
	} );

	root.addEventListener( 'focusout', ( event ) => {
		const target = closestTooltipTarget( event );
		if ( target && target === tooltipTarget ) {
			hideTooltip();
		}
	} );

	root.addEventListener( 'click', ( event ) => {
		if ( event.target instanceof Element && event.target.closest( '[data-wpm-capability-grouping], [data-wpm-capability-source-tab], [data-wpm-select-panel], [data-wpm-select-section]' ) ) {
			hideTooltip();
		}
	} );
}

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '.mandate' ).forEach( ( root ) => {
		root.classList.add( 'is-wpm-enhanced' );
		enhanceSelectionForm( root );
		enhanceBulkControls( root );
		enhanceCapabilityGrouping( root );
		enhanceExpirationSummary( root );
		enhanceTooltips( root );
	} );
} );
