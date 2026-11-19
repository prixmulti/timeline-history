<?php
/**
 * Plugin Name:       ArkivTimeline
 * Plugin URI:        https://github.com/prixmulti/timeline-history
 * Description:       Tidslinjer fra arkiv.dk – historier, Studio, shortcode og Gutenberg-blok.
 * Version:           0.3.76
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Palle Rix
 * Author URI:        https://github.com/prixmulti
 * License:           GPL-2.0-or-later
 * Text Domain:       arkivtimeline
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TIMELINE_HISTORY_VERSION', '0.3.76' );
define( 'TIMELINE_HISTORY_FILE', __FILE__ );
define( 'TIMELINE_HISTORY_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIMELINE_HISTORY_URL', plugin_dir_url( __FILE__ ) );

require_once TIMELINE_HISTORY_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Timeline_History_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Timeline_History_Plugin', 'deactivate' ) );

Timeline_History_Plugin::instance()->init();
