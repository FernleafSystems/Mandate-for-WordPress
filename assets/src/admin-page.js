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

function enhanceTabs( root ) {
	const tabs = Array.from( root.querySelectorAll( '[data-wpm-tab]' ) );
	const panels = Array.from( root.querySelectorAll( '[data-wpm-panel]' ) );
	if ( !tabs.length || !panels.length ) {
		return;
	}

	function activate( group ) {
		tabs.forEach( ( tab ) => {
			const active = tab.dataset.wpmTab === group;
			tab.classList.toggle( 'nav-tab-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			tab.tabIndex = active ? 0 : -1;
		} );

		panels.forEach( ( panel ) => {
			panel.hidden = panel.dataset.wpmPanel !== group;
		} );
	}

	tabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => activate( tab.dataset.wpmTab ) );
	} );

	activate( tabs[ 0 ].dataset.wpmTab );
}

function enhanceBulkControls( root ) {
	root.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-wpm-select-group]' );
		if ( !button || button.disabled ) {
			return;
		}

		const group = button.dataset.wpmSelectGroup;
		const panel = root.querySelector( `[data-wpm-panel="${group}"]` );
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
		if ( event.target instanceof Element && event.target.closest( '[data-wpm-tab]' ) ) {
			hideTooltip();
		}
	} );
}

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '.mandate' ).forEach( ( root ) => {
		root.classList.add( 'is-wpm-enhanced' );
		enhanceSelectionForm( root );
		enhanceTabs( root );
		enhanceBulkControls( root );
		enhanceExpirationSummary( root );
		enhanceTooltips( root );
	} );
} );
