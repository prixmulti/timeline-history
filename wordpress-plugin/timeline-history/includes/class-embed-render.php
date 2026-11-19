<?php
/**
 * Shared frontend embed markup (block + shortcode).
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Embed_Render {

	/**
	 * Allowed markup for empty-state notice inside the embed.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function empty_kses_allowed(): array {
		return array(
			'p' => array(
				'class' => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes or shortcode-derived overrides.
	 * @param WP_Block|null          $block      Block instance when rendering from Gutenberg.
	 */
	public static function render_block( array $attributes, $block = null ): string {
		$overrides = array();
		foreach ( array( 'layout', 'theme', 'skin', 'animation' ) as $key ) {
			if ( ! empty( $attributes[ $key ] ) ) {
				$overrides[ $key ] = sanitize_text_field( (string) $attributes[ $key ] );
			}
		}
		if ( ! empty( $attributes['limit'] ) ) {
			$overrides['max_stories'] = (int) $attributes['limit'];
		}

		$timeline = ! empty( $attributes['timeline'] ) ? sanitize_text_field( (string) $attributes['timeline'] ) : '';

		$config = $timeline !== ''
			? Timeline_History_Sets::config_for_timeline( $timeline, $overrides )
			: Timeline_History_Settings::to_config( $overrides );

		$categories = array();
		if ( ! empty( $attributes['category'] ) ) {
			$categories              = array_map( 'trim', explode( ',', (string) $attributes['category'] ) );
			$config['categorySlugs'] = $categories;
		}

		$wrapper_class = 'th-embed alignwide';
		if ( $block instanceof WP_Block && ! empty( $block->attributes['align'] ) ) {
			$align = sanitize_html_class( (string) $block->attributes['align'] );
			if ( 'full' === $align ) {
				$wrapper_class = 'th-embed alignfull';
			} elseif ( 'wide' === $align ) {
				$wrapper_class = 'th-embed alignwide';
			}
		}

		return self::build_markup( $config, $categories, $timeline, $wrapper_class );
	}

	/**
	 * @param array<string, mixed> $config       Timeline config array.
	 * @param array<int, string>   $categories   Category slugs.
	 * @param string               $timeline     Timeline slug.
	 * @param string               $wrapper_class CSS classes on root embed.
	 */
	public static function build_markup( array $config, array $categories, string $timeline, string $wrapper_class ): string {
		$stories = Timeline_History_Story_Repository::get_stories(
			array(
				'category_slugs' => $categories,
				'timeline'       => $timeline,
				'limit'          => (int) $config['maxStories'],
			)
		);

		Timeline_History_Assets::enqueue_frontend();

		$payload = wp_json_encode(
			Timeline_History_Theme_Adapt::embed_payload( $config, $stories ),
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		if ( ! is_string( $payload ) ) {
			$payload = '{"config":{},"stories":[]}';
		}

		$embed_id = 'th-embed-' . wp_unique_id();

		$empty_html = '';
		if ( empty( $stories ) ) {
			if ( $timeline !== '' ) {
				$empty_html = sprintf(
					'<p class="th-embed__empty">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: timeline slug */
							__( 'Ingen historier på tidslinjen “%s”. Tjek slug i Studio / Arkiv-import, eller at historierne er publiceret.', 'arkivtimeline' ),
							$timeline
						)
					)
				);
			} else {
				$empty_html = '<p class="th-embed__empty">' . esc_html__( 'Ingen historier at vise endnu.', 'arkivtimeline' ) . '</p>';
			}
		}

		$allowed = self::empty_kses_allowed();

		return sprintf(
			'<div class="%1$s" id="%2$s" data-th-embed="%2$s">%3$s<script type="application/json" class="th-embed__data">%4$s</script></div>',
			esc_attr( $wrapper_class ),
			esc_attr( $embed_id ),
			wp_kses( $empty_html, $allowed ),
			$payload
		);
	}
}