import './admin-page.css';

function submitForm( form ) {
	if ( typeof form.requestSubmit === 'function' ) {
		form.requestSubmit();
		return;
	}

	HTMLFormElement.prototype.submit.call( form );
}

function enhanceSelectionForm( root ) {
	const form = root.querySelector( '[data-aps-selection-form]' );
	if ( !form ) {
		return;
	}

	const userSelect = form.querySelector( '#application-password-scoper-user' );
	const passwordSelect = form.querySelector( '#application-password-scoper-password' );

	if ( userSelect ) {
		userSelect.addEventListener( 'change', () => {
			if ( passwordSelect ) {
				passwordSelect.value = '';
				passwordSelect.disabled = true;
			}
			submitForm( form );
		} );
	}

	if ( passwordSelect ) {
		passwordSelect.addEventListener( 'change', () => submitForm( form ) );
	}
}

function enhanceTabs( root ) {
	const tabs = Array.from( root.querySelectorAll( '[data-aps-tab]' ) );
	const panels = Array.from( root.querySelectorAll( '[data-aps-panel]' ) );
	if ( !tabs.length || !panels.length ) {
		return;
	}

	function activate( group ) {
		tabs.forEach( ( tab ) => {
			const active = tab.dataset.apsTab === group;
			tab.classList.toggle( 'nav-tab-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			tab.tabIndex = active ? 0 : -1;
		} );

		panels.forEach( ( panel ) => {
			panel.hidden = panel.dataset.apsPanel !== group;
		} );
	}

	tabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => activate( tab.dataset.apsTab ) );
	} );

	activate( tabs[ 0 ].dataset.apsTab );
}

function enhanceBulkControls( root ) {
	root.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-aps-select-group]' );
		if ( !button ) {
			return;
		}

		const group = button.dataset.apsSelectGroup;
		const panel = root.querySelector( `[data-aps-panel="${group}"]` );
		if ( !panel ) {
			return;
		}

		const checked = button.dataset.apsSelectState === 'checked';
		panel.querySelectorAll( 'input[type="checkbox"][name="allowed_caps[]"], input[type="checkbox"][name="allowed_meta_caps[]"]' )
			.forEach( ( input ) => {
				input.checked = checked;
			} );
	} );
}

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '.application-password-scoper' ).forEach( ( root ) => {
		root.classList.add( 'is-aps-enhanced' );
		enhanceSelectionForm( root );
		enhanceTabs( root );
		enhanceBulkControls( root );
	} );
} );
