<?php
/**
 * Shortcode [timeline_history].
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Shortcode {

	public function __construct() {
		add_shortcode( 'timeline_history', array( $this, 'render' ) );
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'layout'     => '',
				'theme'      => '',
				'skin'       => '',
				'animation'  => '',
				'category'   => '',
				'timeline'   => '',
				'limit'      => 0,
				'show_media' => '',
			),
			$atts,
			'timeline_history'
		);

		$overrides = array();
		foreach ( array( 'layout', 'theme', 'skin', 'animation' ) as $key ) {
			if ( $atts[ $key ] !== '' ) {
				$overrides[ $key ] = sanitize_text_field( $atts[ $key ] );
			}
		}
		if ( $atts['show_media'] !== '' ) {
			$overrides['show_media'] = in_array( $atts['show_media'], array( '1', 'true', 'yes' ), true );
		}
		if ( (int) $atts['limit'] > 0 ) {
			$overrides['max_stories'] = (int) $atts['limit'];
		}

		$timeline = sanitize_text_field( (string) $atts['timeline'] );

		$config = $timeline !== ''
			? Timeline_History_Sets::config_for_timeline( $timeline, $overrides )
			: Timeline_History_Settings::to_config( $overrides );

		$categories = array();
		if ( ! empty( $atts['category'] ) ) {
			$categories              = array_map( 'trim', explode( ',', $atts['category'] ) );
			$config['categorySlugs'] = $categories;
		}

		$stories = Timeline_History_Story_Repository::get_stories(
			array(
				'category_slugs' => $categories,
				'timeline'       => $timeline,
				'limit'          => (int) $config['maxStories'],
			)
		);

		Timeline_History_Assets::enqueue_frontend();

		$payload = wp_json_encode(
			array(
				'config'  => $config,
				'stories' => $stories,
			),
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		if ( ! is_string( $payload ) ) {
			$payload = '{"config":{},"stories":[]}';
		}

		$id = 'th-embed-' . wp_unique_id();

		$empty = '';
		if ( empty( $stories ) ) {
			if ( $timeline !== '' ) {
				$empty = sprintf(
					'<p class="th-embed__empty">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: timeline slug */
							__( 'Ingen historier på tidslinjen “%s”. Tjek slug i Studio / Arkiv-import, eller at historierne er publiceret.', 'timeline-history' ),
							$timeline
						)
					)
				);
			} else {
				$empty = '<p class="th-embed__empty">' . esc_html__( 'Ingen historier at vise endnu.', 'timeline-history' ) . '</p>';
			}
		}

		// Payload in <script type="application/json"> — safer than a huge data-attribute.
		return sprintf(
			'<div class="th-embed alignfull" id="%1$s" data-th-embed="%1$s">%2$s<script type="application/json" class="th-embed__data">%3$s</script></div>',
			esc_attr( $id ),
			$empty,
			$payload
		);
	}
}
