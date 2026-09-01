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
