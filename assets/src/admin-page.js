import './admin-page.css';

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
		if ( !button ) {
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

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '.mandate' ).forEach( ( root ) => {
		root.classList.add( 'is-wpm-enhanced' );
		enhanceSelectionForm( root );
		enhanceTabs( root );
		enhanceBulkControls( root );
	} );
} );
