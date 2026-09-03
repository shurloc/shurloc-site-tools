/**
 * Divi WooCommerce breadcrumb separator.
 *
 * @package ShurlocSiteTools
 */

(function () {
	'use strict';

	const breadcrumbSelector =
		'.et_pb_wc_breadcrumb .woocommerce-breadcrumb';

	const svgNamespace = 'http://www.w3.org/2000/svg';

	/**
	 * Create a solid SVG breadcrumb separator.
	 *
	 * @returns {HTMLSpanElement}
	 */
	function createSeparator() {
		const span = document.createElement('span');
		const svg = document.createElementNS(svgNamespace, 'svg');
		const polygon = document.createElementNS(svgNamespace, 'polygon');

		span.className = 'shurloc-breadcrumb-separator';
		span.setAttribute('aria-hidden', 'true');

		svg.setAttribute('viewBox', '0 0 8 12');
		svg.setAttribute('focusable', 'false');
		svg.setAttribute('aria-hidden', 'true');

		polygon.setAttribute('points', '1,0 7,6 1,12');
		polygon.setAttribute('fill', 'currentColor');

		svg.appendChild(polygon);
		span.appendChild(svg);

		return span;
	}

	/**
	 * Replace separator text nodes in a breadcrumb.
	 *
	 * Handles both standalone separator nodes and the final text node
	 * containing the separator followed by the current product name.
	 *
	 * @param {Element} breadcrumb Breadcrumb container.
	 *
	 * @returns {void}
	 */
	function replaceSeparators(breadcrumb) {
		const childNodes = Array.from(breadcrumb.childNodes);

		childNodes.forEach(function (node) {
			let match;
			let remainder;
			let separator;

			if (node.nodeType !== Node.TEXT_NODE) {
				return;
			}

			if (!node.textContent) {
				return;
			}

			match = node.textContent.match(/^\s*\/\s*(.*)$/s);

			if (!match) {
				return;
			}

			remainder = match[1];
			separator = createSeparator();

			if (remainder === '') {
				node.replaceWith(separator);
				return;
			}

			node.replaceWith(
				separator,
				document.createTextNode(remainder)
			);
		});
	}

	/**
	 * Replace separators in all Divi Woo Breadcrumb modules.
	 *
	 * @returns {void}
	 */
	function initializeBreadcrumbSeparators() {
		document
			.querySelectorAll(breadcrumbSelector)
			.forEach(replaceSeparators);
	}

	if (document.readyState === 'loading') {
		document.addEventListener(
			'DOMContentLoaded',
			initializeBreadcrumbSeparators
		);
	} else {
		initializeBreadcrumbSeparators();
	}
}());
