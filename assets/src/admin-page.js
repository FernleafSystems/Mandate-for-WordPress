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

		const button = event.target.closest( '[data-wpm-select-panel]' );
		if ( !button || button.disabled ) {
			return;
		}

		const panel = button.closest( '[data-wpm-capability-panel]' );
		if ( !panel ) {
			return;
		}

		const checked = button.dataset.wpmSelectState === 'checked';
		panel.querySelectorAll( 'input[type="checkbox"][name="allowed_caps[]"], input[type="checkbox"][name="allowed_meta_caps[]"]' )
			.forEach( ( input ) => {
				input.checked = checked;
			} );
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

function capabilityItemAttribute( item, mode ) {
	return mode === 'action' ? item.dataset.wpmCapabilityAction : item.dataset.wpmCapabilityArea;
}

function capabilityItemSubAttribute( item, mode ) {
	return mode === 'action' ? item.dataset.wpmCapabilityArea : item.dataset.wpmCapabilityAction;
}

function createBulkButton( action ) {
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'button';
	button.dataset.wpmSelectPanel = '';
	button.dataset.wpmSelectState = action.state;
	button.textContent = action.label;
	button.disabled = Boolean( action.disabled );
	return button;
}

function createCapabilitySection( mode, group, subgroup, items ) {
	const section = document.createElement( 'fieldset' );
	section.id = `mandate-${mode}-${group.key}-${subgroup.key}-capabilities`;
	section.className = 'mandate-capability-section';

	const legend = document.createElement( 'legend' );
	legend.textContent = subgroup.label;
	section.appendChild( legend );

	const list = document.createElement( 'div' );
	list.className = 'mandate-capability-list';
	items.forEach( ( item ) => list.appendChild( item ) );
	section.appendChild( list );

	return section;
}

function createCapabilityPanel( mode, group, config, allItems ) {
	const sections = [];
	group.subgroups.forEach( ( subgroup ) => {
		const items = allItems.filter( ( item ) => (
			capabilityItemAttribute( item, mode ) === group.key
			&& capabilityItemSubAttribute( item, mode ) === subgroup.key
		) );
		if ( items.length ) {
			sections.push( createCapabilitySection( mode, group, subgroup, items ) );
		}
	} );

	if ( !sections.length ) {
		return null;
	}

	const panel = document.createElement( 'section' );
	panel.id = `mandate-capability-${mode}-${group.key}`;
	panel.className = 'mandate-capability-panel';
	panel.dataset.wpmCapabilityPanel = group.key;

	const heading = document.createElement( 'div' );
	heading.className = 'mandate-panel-heading';
	const title = document.createElement( 'h3' );
	title.textContent = group.label;
	heading.appendChild( title );

	const actions = document.createElement( 'p' );
	actions.appendChild( createBulkButton( config.bulkActions.selectAll ) );
	actions.appendChild( document.createTextNode( ' ' ) );
	actions.appendChild( createBulkButton( config.bulkActions.deselectAll ) );
	heading.appendChild( actions );
	panel.appendChild( heading );

	const scroll = document.createElement( 'div' );
	scroll.className = 'mandate-capability-scroll';
	sections.forEach( ( section ) => scroll.appendChild( section ) );
	panel.appendChild( scroll );

	return panel;
}

function renderCapabilityGroups( container, config, mode ) {
	const modeConfig = config.modes[ mode ];
	if ( !modeConfig ) {
		return;
	}

	const allItems = Array.from( container.querySelectorAll( '[data-wpm-capability-item]' ) );
	const fragment = document.createDocumentFragment();
	modeConfig.groups.forEach( ( group ) => {
		const panel = createCapabilityPanel( mode, group, config, allItems );
		if ( panel ) {
			fragment.appendChild( panel );
		}
	} );

	container.replaceChildren( fragment );
	container.dataset.wpmCapabilityMode = mode;
}

function enhanceCapabilityGrouping( root ) {
	const form = root.querySelector( '.mandate-scope-form' );
	if ( !form ) {
		return;
	}

	const container = form.querySelector( '[data-wpm-capability-groups]' );
	const config = container ? parseGroupingConfig( container ) : null;
	const controls = Array.from( form.querySelectorAll( '[data-wpm-capability-grouping-mode]' ) );
	if ( !container || !config || !controls.length ) {
		return;
	}

	controls.forEach( ( control ) => {
		control.addEventListener( 'change', () => {
			if ( control.checked ) {
				hideTooltip();
				renderCapabilityGroups( container, config, control.value );
			}
		} );
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
		if ( event.target instanceof Element && event.target.closest( '[data-wpm-capability-grouping], [data-wpm-select-panel]' ) ) {
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
