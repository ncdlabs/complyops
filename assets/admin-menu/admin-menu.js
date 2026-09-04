/**
 * ComplyOps right admin menu: collapse toggle and wp-admin menu fold.
 */
( function () {
	'use strict';

	const STORAGE_KEY = 'complyops-right-menu-collapsed';

	function initializeAdminMenu() {
		const body = document.body;

		if ( ! body || ! body.classList.contains( 'complyops-admin-screen' ) ) {
			return;
		}

		const sidebar = document.getElementById( 'complyops-right-menu' );
		const toggle = document.getElementById( 'complyops-right-menu-toggle' );
		if ( ! sidebar || ! toggle ) {
			return;
		}

		const backdrop = document.getElementById( 'complyops-right-menu-backdrop' );
		const wpCollapseButton = document.getElementById( 'collapse-button' );

		function syncWordPressMenuCollapsed() {
			body.classList.add( 'folded' );
			if ( wpCollapseButton ) {
				wpCollapseButton.setAttribute( 'aria-expanded', 'false' );
			}
		}

		function setRightMenuCollapsed( collapsed ) {
			const label = collapsed
				? toggle.getAttribute( 'data-expand-label' ) || 'Expand menu'
				: toggle.getAttribute( 'data-collapse-label' ) || 'Collapse menu';
			const labelEl = toggle.querySelector( '.complyops-right-menu__toggle-label' );

			body.classList.toggle( 'complyops-right-menu-collapsed', collapsed );
			body.classList.toggle( 'complyops-right-menu-expanded', ! collapsed );
			sidebar.classList.toggle( 'is-collapsed', collapsed );
			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			toggle.setAttribute( 'title', label );
			if ( labelEl ) {
				labelEl.textContent = label;
			}
			try {
				window.localStorage.setItem( STORAGE_KEY, collapsed ? '1' : '0' );
			} catch ( error ) {
				// Ignore storage failures (private mode, quota, etc.).
			}
		}

		syncWordPressMenuCollapsed();

		let stored = '';
		try {
			stored = window.localStorage.getItem( STORAGE_KEY ) || '';
		} catch ( error ) {
			stored = '';
		}
		setRightMenuCollapsed(
			window.matchMedia( '(max-width: 782px)' ).matches || stored === '1'
		);

		toggle.addEventListener( 'click', function () {
			setRightMenuCollapsed(
				! body.classList.contains( 'complyops-right-menu-collapsed' )
			);
		} );

		if ( backdrop ) {
			backdrop.addEventListener( 'click', function () {
				setRightMenuCollapsed( true );
				toggle.focus();
			} );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if (
				event.key === 'Escape' &&
				window.matchMedia( '(max-width: 782px)' ).matches &&
				! body.classList.contains( 'complyops-right-menu-collapsed' )
			) {
				setRightMenuCollapsed( true );
				toggle.focus();
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initializeAdminMenu );
	} else {
		initializeAdminMenu();
	}
} )();
