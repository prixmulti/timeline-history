<?php
/**
 * Dynamic block render.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$overrides = array();
foreach ( array( 'layout', 'theme', 'skin', 'animation' ) as $key ) {
	if ( ! empty( $attributes[ $key ] ) ) {
		$overrides[ $key ] = sanitize_text_field( $attributes[ $key ] );
	}
}
if ( ! empty( $attributes['limit'] ) ) {
	$overrides['max_stories'] = (int) $attributes['limit'];
}

$config = Timeline_History_Settings::to_config( $overrides );

$categories = array();
if ( ! empty( $attributes['category'] ) ) {
	$categories              = array_map( 'trim', explode( ',', $attributes['category'] ) );
	$config['categorySlugs'] = $categories;
}

$timeline = ! empty( $attributes['timeline'] ) ? sanitize_text_field( $attributes['timeline'] ) : '';

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

printf(
	'<div class="th-embed alignfull" id="%1$s" data-th-embed="%1$s">%2$s<script type="application/json" class="th-embed__data">%3$s</script></div>',
	esc_attr( $id ),
	$empty,
	$payload
);
