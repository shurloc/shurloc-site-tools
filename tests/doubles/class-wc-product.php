<?php
/**
 * WooCommerce product test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

/**
 * Registered WooCommerce products for tests.
 *
 * @var array<int,WC_Product> $GLOBALS['shurloc_test_products']
 */
$GLOBALS['shurloc_test_products'] = array();

/**
 * WooCommerce product test double.
 */
if ( ! class_exists( 'WC_Product' ) ) {

	/**
	 * WooCommerce product test double.
	 */
	class WC_Product {

		/**
		 * Product ID.
		 *
		 * @var int
		 */
		private int $id;

		/**
		 * Product name.
		 *
		 * @var string
		 */
		private string $name = '';

		/**
		 * Short description.
		 *
		 * @var string
		 */
		private string $short_description = '';

		/**
		 * Product description.
		 *
		 * @var string
		 */
		private string $description = '';

		/**
		 * Product category.
		 *
		 * @var string
		 */
		private string $category = '';

		/**
		 * Product SKU.
		 *
		 * @var string
		 */
		private string $sku = '';

		/**
		 * Product price.
		 *
		 * @var string
		 */
		private string $price = '';

		/**
		 * Product regular price.
		 *
		 * @var string
		 */
		private string $regular_price = '';

		/**
		 * Product sale price.
		 *
		 * @var string
		 */
		private string $sale_price = '';

		/**
		 * Stock status.
		 *
		 * @var string
		 */
		private string $stock_status = 'instock';

		/**
		 * Product type.
		 *
		 * @var string
		 */
		private string $type = 'simple';

		/**
		 * Child product IDs.
		 *
		 * @var int[]
		 */
		private array $children = array();

		/**
		 * Product image ID.
		 *
		 * @var int
		 */
		private int $image_id = 0;

		/**
		 * Rating count.
		 *
		 * @var int
		 */
		private int $rating_count = 0;

		/**
		 * Average rating.
		 *
		 * @var string
		 */
		private string $average_rating = '0';

		/**
		 * Review count.
		 *
		 * @var int
		 */
		private int $review_count = 0;

		/**
		 * Product status.
		 *
		 * @var string
		 */
		private string $status = 'publish';

		/**
		 * Whether the product is visible.
		 *
		 * @var bool
		 */
		private bool $visible = true;

		/**
		 * Upsell product IDs.
		 *
		 * @var int[]
		 */
		private array $upsell_ids = array();

		/**
		 * Constructor.
		 *
		 * @param int $id Product ID. Defaults to zero.
		 */
		public function __construct(
			int $id = 0
		) {

			$this->id = $id;

			$GLOBALS['shurloc_test_products'][ $id ] = $this;
		}

		/**
		 * Get product ID.
		 *
		 * @return int Product ID.
		 */
		public function get_id(): int {

			return $this->id;
		}

		/**
		 * Set product name.
		 *
		 * @param string $name Product name.
		 * @return void
		 */
		public function set_name(
			string $name
		): void {

			$this->name = $name;
		}

		/**
		 * Get product name.
		 *
		 * @return string Product name.
		 */
		public function get_name(): string {

			return $this->name;
		}

		/**
		 * Set short description.
		 *
		 * @param string $short_description Short description.
		 * @return void
		 */
		public function set_short_description(
			string $short_description
		): void {

			$this->short_description = $short_description;
		}

		/**
		 * Get short description.
		 *
		 * @return string Short description.
		 */
		public function get_short_description(): string {

			return $this->short_description;
		}

		/**
		 * Set description.
		 *
		 * @param string $description Product description.
		 * @return void
		 */
		public function set_description(
			string $description
		): void {

			$this->description = $description;
		}

		/**
		 * Get description.
		 *
		 * @return string Product description.
		 */
		public function get_description(): string {

			return $this->description;
		}

		/**
		 * Get product category.
		 *
		 * @return string Product category.
		 */
		public function get_category(): string {

			return $this->category;
		}

		/**
		 * Set SKU.
		 *
		 * @param string $sku Product SKU.
		 * @return void
		 */
		public function set_sku(
			string $sku
		): void {

			$this->sku = $sku;
		}

		/**
		 * Get SKU.
		 *
		 * @return string Product SKU.
		 */
		public function get_sku(): string {

			return $this->sku;
		}

		/**
		 * Set current price.
		 *
		 * @param string $price Product price.
		 * @return void
		 */
		public function set_price(
			string $price
		): void {

			$this->price = $price;
		}

		/**
		 * Get current price.
		 *
		 * @return string Product price.
		 */
		public function get_price(): string {

			return $this->price;
		}

		/**
		 * Set regular price.
		 *
		 * @param string $price Regular price.
		 * @return void
		 */
		public function set_regular_price(
			string $price
		): void {

			$this->regular_price = $price;
		}

		/**
		 * Get regular price.
		 *
		 * @return string Regular price.
		 */
		public function get_regular_price(): string {

			return $this->regular_price;
		}

		/**
		 * Set sale price.
		 *
		 * @param string $price Sale price.
		 * @return void
		 */
		public function set_sale_price(
			string $price
		): void {

			$this->sale_price = $price;
		}

		/**
		 * Get sale price.
		 *
		 * @return string Sale price.
		 */
		public function get_sale_price(): string {

			return $this->sale_price;
		}

		/**
		 * Set stock status.
		 *
		 * @param string $status Stock status.
		 * @return void
		 */
		public function set_stock_status(
			string $status
		): void {

			$this->stock_status = $status;
		}

		/**
		 * Determine whether product is in stock.
		 *
		 * @return bool Stock status.
		 */
		public function is_in_stock(): bool {

			return 'instock' === $this->stock_status;
		}

		/**
		 * Determine product type.
		 *
		 * @param string $type Requested type.
		 * @return bool Whether product matches type.
		 */
		public function is_type(
			string $type
		): bool {

			return $this->type === $type;
		}

		/**
		 * Get child product IDs.
		 *
		 * @return int[] Child IDs.
		 */
		public function get_children(): array {

			return $this->children;
		}

		/**
		 * Set image ID.
		 *
		 * @param int $image_id Image attachment ID.
		 * @return void
		 */
		public function set_image_id(
			int $image_id
		): void {

			$this->image_id = $image_id;
		}

		/**
		 * Get image ID.
		 *
		 * @return int Image ID.
		 */
		public function get_image_id(): int {

			return $this->image_id;
		}

		/**
		 * Set rating count.
		 *
		 * @param int $count Rating count.
		 * @return void
		 */
		public function set_rating_count(
			int $count
		): void {

			$this->rating_count = $count;
		}

		/**
		 * Get rating count.
		 *
		 * @return int Rating count.
		 */
		public function get_rating_count(): int {

			return $this->rating_count;
		}

		/**
		 * Set average rating.
		 *
		 * @param string $rating Average rating.
		 * @return void
		 */
		public function set_average_rating(
			string $rating
		): void {

			$this->average_rating = $rating;
		}

		/**
		 * Get average rating.
		 *
		 * @return string Average rating.
		 */
		public function get_average_rating(): string {

			return $this->average_rating;
		}

		/**
		 * Set review count.
		 *
		 * @param int $count Review count.
		 * @return void
		 */
		public function set_review_count(
			int $count
		): void {

			$this->review_count = $count;
		}

		/**
		 * Get review count.
		 *
		 * @return int Review count.
		 */
		public function get_review_count(): int {

			return $this->review_count;
		}

		/**
		 * Set product status.
		 *
		 * @param string $status Product status.
		 *
		 * @return void
		 */
		public function set_status(
			string $status
		): void {

			$this->status = $status;
		}

		/**
		 * Get product status.
		 *
		 * @return string Product status.
		 */
		public function get_status(): string {

			return $this->status;
		}

		/**
		 * Determine whether the product is visible.
		 *
		 * @return bool Whether the product is visible.
		 */
		public function is_visible(): bool {

			return $this->visible;
		}

		/**
		 * Set upsell product IDs.
		 *
		 * @param int[] $upsell_ids Upsell product IDs.
		 *
		 * @return void
		 */
		public function set_upsell_ids(
			array $upsell_ids
		): void {

			$this->upsell_ids = $upsell_ids;
		}

		/**
		 * Get upsell product IDs.
		 *
		 * @return int[] Upsell product IDs.
		 */
		public function get_upsell_ids(): array {

			return $this->upsell_ids;
		}
	}
}
