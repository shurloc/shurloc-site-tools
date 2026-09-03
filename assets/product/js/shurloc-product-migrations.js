/**
 * Product migrations admin behavior.
 *
 * @package ShurlocSiteTools
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const forms = document.querySelectorAll( '.shurloc-migration-form' );
		const overlay = document.querySelector( '.shurloc-migration-overlay' );

		forms.forEach( function ( form ) {
			const enableCheckbox = form.querySelector(
				'.shurloc-migration-enable'
			);

			const submitButton = form.querySelector(
				'.shurloc-migration-submit'
			);

			if ( ! enableCheckbox || ! submitButton ) {
				return;
			}

			enableCheckbox.addEventListener( 'change', function () {
				submitButton.disabled = ! enableCheckbox.checked;
			} );

			form.addEventListener( 'submit', function ( event ) {
				const confirmMessage = form.dataset.confirmMessage;

				if (
					confirmMessage &&
					! window.confirm( confirmMessage )
				) {
					event.preventDefault();
					return;
				}

				submitButton.disabled = true;

				if ( overlay ) {
					overlay.hidden = false;
				}
			} );
		} );
	} );
}() );
