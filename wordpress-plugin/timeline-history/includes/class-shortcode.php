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
		// Before do_shortcode (11): Word/Gutenberg “smart quotes” break the shortcode regex.
		add_filter( 'the_content', array( $this, 'normalize_timeline_shortcode_quotes' ), 9 );
		add_filter( 'widget_text_content', array( $this, 'normalize_timeline_shortcode_quotes' ), 9 );
	}

	/**
	 * Replace curly quotes inside [timeline_history …] so WordPress parses the tag.
	 */
	public function normalize_timeline_shortcode_quotes( string $content ): string {
		if ( strpos( $content, 'timeline_history' ) === false ) {
			return $content;
		}

		return preg_replace_callback(
			'/\[timeline_history\b[^\]]*\]/iu',
			static function ( array $m ): string {
				return str_replace(
					array( "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}" ),
					array( '"', '"', "'", "'" ),
					$m[0]
				);
			},
			$content
		) ?? $content;
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

		$attributes = array(
			'layout'    => $atts['layout'],
			'theme'     => $atts['theme'],
			'skin'      => $atts['skin'],
			'animation' => $atts['animation'],
			'category'  => $atts['category'],
			'timeline'  => $atts['timeline'],
			'limit'     => (int) $atts['limit'],
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
			$categories = array_map( 'trim', explode( ',', $atts['category'] ) );
		}

		return Timeline_History_Embed_Render::build_markup(
			$config,
			$categories,
			$timeline,
			'th-embed alignwide'
		);
	}
}