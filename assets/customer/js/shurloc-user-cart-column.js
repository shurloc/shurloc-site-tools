/**
 * User cart column.
 *
 * Handles opening and closing cart detail panels on the Users screen.
 *
 * @package ShurlocSiteTools
 */

document.addEventListener( 'click', function ( event ) {

	const toggle = event.target.closest( '.shurloc-cart-toggle' );

	if ( toggle ) {
		event.preventDefault();

		const panelId = toggle.dataset.target;
		const panel = document.getElementById( panelId );

		if ( ! panel ) {
			return;
		}

		document
			.querySelectorAll( '.shurloc-cart-panel.open' )
			.forEach( function ( openPanel ) {

				if ( openPanel !== panel ) {
					openPanel.classList.remove( 'open' );
				}
			} );

		panel.classList.toggle( 'open' );

		if ( panel.classList.contains( 'open' ) ) {
			positionPanel( panel, toggle );
		}

		return;
	}

	const close = event.target.closest( '.shurloc-cart-close' );

	if ( close ) {
		event.preventDefault();

		const panel = close.closest( '.shurloc-cart-panel' );

		if ( panel ) {
			panel.classList.remove( 'open' );
		}

		return;
	}

	if ( ! event.target.closest( '.shurloc-cart-wrap' ) ) {
		document
			.querySelectorAll( '.shurloc-cart-panel.open' )
			.forEach( function ( panel ) {
				panel.classList.remove( 'open' );
			} );
	}
} );

/**
 * Position a cart panel below its toggle while keeping it inside the viewport.
 *
 * @param {HTMLElement} panel  Cart details panel.
 * @param {HTMLElement} toggle Cart count toggle.
 */
function positionPanel( panel, toggle ) {
	const gutter = 8;
	const toggleRect = toggle.getBoundingClientRect();
	const panelWidth = panel.getBoundingClientRect().width;
	const maximumLeft = window.innerWidth - panelWidth - gutter;
	const left = Math.max( gutter, Math.min( toggleRect.left, maximumLeft ) );

	panel.style.left = `${ left }px`;
	panel.style.top = `${ toggleRect.bottom + 6 }px`;
}

window.addEventListener( 'resize', function () {
	const openPanel = document.querySelector( '.shurloc-cart-panel.open' );

	if ( openPanel ) {
		const toggle = document.querySelector(
			`.shurloc-cart-toggle[data-target="${ openPanel.id }"]`
		);

		if ( toggle ) {
			positionPanel( openPanel, toggle );
		}
	}
} );

window.addEventListener( 'scroll', function () {
	const openPanel = document.querySelector( '.shurloc-cart-panel.open' );

	if ( openPanel ) {
		const toggle = document.querySelector(
			`.shurloc-cart-toggle[data-target="${ openPanel.id }"]`
		);

		if ( toggle ) {
			positionPanel( openPanel, toggle );
		}
	}
} );
