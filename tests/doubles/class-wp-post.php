<?php
/**
 * WordPress post test double.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Post' ) ) {

	/**
	 * WordPress post test double.
	 */
	class WP_Post {

		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public int $ID;

		/**
		 * Post content.
		 *
		 * @var string
		 */
		public string $post_content;

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public string $post_type;

		/**
		 * Constructor.
		 *
		 * @param object $post Post data.
		 */
		public function __construct(
			object $post
		) {
			$this->ID = isset( $post->ID )
				? (int) $post->ID
				: 0;

			$this->post_content = isset( $post->post_content )
				? (string) $post->post_content
				: '';

			$this->post_type = isset( $post->post_type )
				? (string) $post->post_type
				: '';
		}
	}
}
