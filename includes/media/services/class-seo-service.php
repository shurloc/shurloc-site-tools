<?php
/**
 * Media SEO service.
 *
 * Provides SEO-related attachment metadata for WordPress media.
 *
 * @package ShurlocSiteTools
 */

declare( strict_types=1 );

namespace Shurloc\SiteTools\Media\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Provides SEO-related attachment metadata.
 */
final class SEO_Service {

	/**
	 * Attachment image alt-text meta key.
	 *
	 * @var string
	 */
	public const ALT_TEXT_META_KEY =
		'_wp_attachment_image_alt';

	/**
	 * Get an attachment's alt text.
	 *
	 * Whitespace surrounding the stored value is removed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public function get_alt_text(
		int $attachment_id
	): string {

		if ( 0 >= $attachment_id ) {
			return '';
		}

		$alt_text = get_post_meta(
			$attachment_id,
			self::ALT_TEXT_META_KEY,
			true
		);

		if ( ! is_string( $alt_text ) ) {
			return '';
		}

		return trim( $alt_text );
	}

	/**
	 * Determine whether an attachment has alt text.
	 *
	 * Empty or whitespace-only alt text is treated as missing.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function has_alt_text(
		int $attachment_id
	): bool {

		return '' !== $this->get_alt_text(
			attachment_id: $attachment_id,
		);
	}
}
