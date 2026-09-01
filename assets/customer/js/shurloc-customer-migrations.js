/**
 * Customer migrations.
 *
 * Handles migration confirmation and running-state behavior.
 *
 * @package ShurlocSiteTools
 */

document.addEventListener( 'DOMContentLoaded', () => {

	document
		.querySelectorAll( '.shurloc-migration-form' )
		.forEach( ( form ) => {

			const checkbox = form.querySelector(
				'.shurloc-migration-enable'
			);

			const button = form.querySelector(
				'.shurloc-migration-submit'
			);

			const overlay = document.querySelector(
				'.shurloc-migration-overlay'
			);

			if ( ! checkbox || ! button ) {
				return;
			}

			checkbox.addEventListener( 'change', () => {
				button.disabled = ! checkbox.checked;
			} );

			form.addEventListener( 'submit', ( event ) => {

				if ( ! checkbox.checked ) {
					event.preventDefault();

					return;
				}

				const message =
					form.dataset.confirmMessage ||
					'Run this migration?';

				if ( ! window.confirm( message ) ) {
					event.preventDefault();

					checkbox.checked = false;
					button.disabled = true;

					return;
				}

				checkbox.disabled = true;
				button.disabled = true;
				button.textContent = 'Migration running…';

				if ( overlay ) {
					overlay.hidden = false;
				}
			} );
		} );
} );
