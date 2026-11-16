<?php
/**
 * Dynamic block render.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks (unused).
 * @var WP_Block $block      Block instance.
 */
return Timeline_History_Embed_Render::render_block( $attributes, $block );