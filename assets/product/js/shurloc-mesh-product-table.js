/**
 * Mesh product table interactions.
 *
 * Synchronizes mesh specification table rows with the WooCommerce
 * select-mesh-count variation dropdown.
 *
 * @package ShurlocSiteTools
 */

(function () {
	'use strict';

	const tableSelector = '.shurloc-mesh-specification-table';
	const rowSelector = '.shurloc-mesh-table-row';
	const selectSelector = 'select[name="attribute_select-mesh-count"]';
	const selectedClass = 'shurloc-mesh-table-row-selected';

	/**
	 * Find the WooCommerce mesh variation select associated with a table.
	 *
	 * @param {HTMLElement} table Mesh specification table.
	 * @return {HTMLSelectElement|null} Associated variation select.
	 */
	function findVariationSelect(table) {
		const product = table.closest('.product');

		if (product) {
			const productSelect = product.querySelector(selectSelector);

			if (productSelect instanceof HTMLSelectElement) {
				return productSelect;
			}
		}

		const form = table
			.closest('.shurloc-mesh-table-wrapper')
			?.parentElement
			?.querySelector(selectSelector);

		if (form instanceof HTMLSelectElement) {
			return form;
		}

		const documentSelect = document.querySelector(selectSelector);

		if (documentSelect instanceof HTMLSelectElement) {
			return documentSelect;
		}

		return null;
	}

	/**
	 * Mark the row matching the currently selected variation.
	 *
	 * @param {HTMLElement} table Mesh specification table.
	 * @param {string} variationValue Selected variation value.
	 * @return {void}
	 */
	function selectMatchingRow(table, variationValue) {
		const rows = table.querySelectorAll(rowSelector);

		rows.forEach(function (row) {
            const isSelected =
                variationValue !== '' &&
                row.dataset.variationValue === variationValue;

            row.classList.toggle(selectedClass, isSelected);

            row.setAttribute(
                'aria-selected',
                isSelected ? 'true' : 'false'
            );
        });
	}

	/**
	 * Select a WooCommerce variation from a table row.
	 *
	 * @param {HTMLElement} row Mesh specification table row.
	 * @param {HTMLSelectElement} select WooCommerce variation select.
	 * @param {HTMLElement} table Mesh specification table.
	 * @return {void}
	 */
	function selectVariationFromRow(row, select, table) {
		const variationValue = row.dataset.variationValue;

		if (!variationValue) {
			return;
		}

		const matchingOption = Array.from(select.options).find(
			function (option) {
				return (
					option.value === variationValue &&
					!option.disabled
				);
			}
		);

		if (!matchingOption) {
			return;
		}

		select.value = variationValue;

		select.dispatchEvent(
			new Event('change', {
				bubbles: true,
			})
		);

		selectMatchingRow(table, variationValue);
	}

	/**
	 * Initialize one mesh specification table.
	 *
	 * @param {HTMLElement} table Mesh specification table.
	 * @return {void}
	 */
	function initializeTable(table) {
		const select = findVariationSelect(table);

		if (!select) {
			return;
		}

		const rows = table.querySelectorAll(rowSelector);

		rows.forEach(function (row) {
			row.setAttribute('aria-selected', 'false');

			row.addEventListener('click', function () {
				selectVariationFromRow(row, select, table);
			});

			row.addEventListener('keydown', function (event) {
				if (
					event.key !== 'Enter' &&
					event.key !== ' '
				) {
					return;
				}

				event.preventDefault();

				selectVariationFromRow(row, select, table);
			});
		});

		select.addEventListener('change', function () {
			selectMatchingRow(table, select.value);
		});

		selectMatchingRow(table, select.value);
	}

	/**
	 * Initialize all mesh specification tables.
	 *
	 * @return {void}
	 */
	function initializeMeshTables() {
		const tables = document.querySelectorAll(tableSelector);

		tables.forEach(function (table) {
			initializeTable(table);
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener(
			'DOMContentLoaded',
			initializeMeshTables
		);
	} else {
		initializeMeshTables();
	}
}());
