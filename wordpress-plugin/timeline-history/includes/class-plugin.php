<?php
/**
 * Plugin bootstrap.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TIMELINE_HISTORY_DIR . 'includes/class-timeline-sets.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-post-types.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-meta.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-settings.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-theme-adapt.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-story-repository.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-embed-render.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-shortcode.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-assets.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-admin-builder.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-arkiv-client.php';
require_once TIMELINE_HISTORY_DIR . 'includes/class-admin-arkiv-import.php';
require_once TIMELINE_HISTORY_DIR . 'includes/rest/class-preview-controller.php';
require_once TIMELINE_HISTORY_DIR . 'includes/rest/class-stories-controller.php';

final class Timeline_History_Plugin {

	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		Timeline_History_Sets::boot();
		new Timeline_History_Post_Types();
		new Timeline_History_Meta();
		new Timeline_History_Settings();
		new Timeline_History_Shortcode();
		new Timeline_History_Assets();
		new Timeline_History_Admin_Builder();
		new Timeline_History_Admin_Arkiv_Import();
		new Timeline_History_Preview_Controller();
		new Timeline_History_Stories_Controller();

		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block(): void {
		$block_json = TIMELINE_HISTORY_DIR . 'blocks/timeline/block.json';
		if ( file_exists( $block_json ) ) {
			register_block_type_from_metadata( $block_json );
		}
	}

	/**
	 * Register CPT/taxonomy then flush rewrites so permalinks work immediately.
	 */
	public static function activate(): void {
		require_once TIMELINE_HISTORY_DIR . 'includes/class-post-types.php';
		Timeline_History_Post_Types::register();
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrites on deactivate so CPT/taxonomy routes are removed.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
