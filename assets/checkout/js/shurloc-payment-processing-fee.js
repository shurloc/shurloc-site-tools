/**
 * Payment processing fee checkout refresh.
 *
 * @package ShurlocSiteTools
 */

(function ($) {
	'use strict';

	$(function () {
		$('form.checkout').on(
			'change',
			'input[name="payment_method"]',
			function () {
				$('body').trigger('update_checkout');
			}
		);
	});
})(jQuery);
