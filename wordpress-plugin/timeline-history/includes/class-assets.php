<?php
/**
 * Script and style registration.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Assets {

	private static $frontend_enqueued = false;
	private static $builder_enqueued  = false;

	/**
	 * @return array{dependencies: array<int, string>, version: string}
	 */
	private static function script_asset( string $basename ): array {
		$file = TIMELINE_HISTORY_DIR . 'assets/dist/' . $basename . '.asset.php';
		if ( ! file_exists( $file ) ) {
			return array(
				'dependencies' => array(),
				'version'      => TIMELINE_HISTORY_VERSION,
			);
		}
		$asset = include $file;
		if ( ! is_array( $asset ) ) {
			return array(
				'dependencies' => array(),
				'version'      => TIMELINE_HISTORY_VERSION,
			);
		}
		return array(
			'dependencies' => isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			'version'      => isset( $asset['version'] ) ? (string) $asset['version'] : TIMELINE_HISTORY_VERSION,
		);
	}

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend' ) );
		add_action( 'enqueue_block_assets', array( $this, 'block_editor_styles' ) );
	}

	/**
	 * Register handles early so late enqueue from shortcode/block still prints in footer.
	 */
	public function register_frontend(): void {
		$dir = TIMELINE_HISTORY_DIR . 'assets/dist/';
		$url = TIMELINE_HISTORY_URL . 'assets/dist/';

		if ( file_exists( $dir . 'timeline.css' ) ) {
			wp_register_style(
				'timeline-history-view',
				$url . 'timeline.css',
				array(),
				TIMELINE_HISTORY_VERSION
			);
		}

		if ( file_exists( $dir . 'view.js' ) ) {
			$view_asset = self::script_asset( 'view' );
			wp_register_script(
				'timeline-history-view',
				$url . 'view.js',
				$view_asset['dependencies'],
				$view_asset['version'],
				true
			);
		}
	}

	public static function enqueue_frontend(): void {
		if ( self::$frontend_enqueued ) {
			return;
		}
		self::$frontend_enqueued = true;

		// Ensure handles exist even if called before wp_enqueue_scripts (edge cases).
		$dir = TIMELINE_HISTORY_DIR . 'assets/dist/';
		$url = TIMELINE_HISTORY_URL . 'assets/dist/';

		if ( ! wp_style_is( 'timeline-history-view', 'registered' ) && file_exists( $dir . 'timeline.css' ) ) {
			wp_register_style(
				'timeline-history-view',
				$url . 'timeline.css',
				array(),
				TIMELINE_HISTORY_VERSION
			);
		}
		if ( ! wp_script_is( 'timeline-history-view', 'registered' ) && file_exists( $dir . 'view.js' ) ) {
			$view_asset = self::script_asset( 'view' );
			wp_register_script(
				'timeline-history-view',
				$url . 'view.js',
				$view_asset['dependencies'],
				$view_asset['version'],
				true
			);
		}

		if ( wp_style_is( 'timeline-history-view', 'registered' ) ) {
			wp_enqueue_style( 'timeline-history-view' );
		}
		if ( wp_script_is( 'timeline-history-view', 'registered' ) ) {
			wp_enqueue_script( 'timeline-history-view' );
		}
	}

	public function block_editor_styles(): void {
		if ( ! is_admin() ) {
			return;
		}
		$url = TIMELINE_HISTORY_URL . 'assets/dist/timeline.css';
		if ( file_exists( TIMELINE_HISTORY_DIR . 'assets/dist/timeline.css' ) ) {
			wp_enqueue_style( 'timeline-history-editor-timeline', $url, array(), TIMELINE_HISTORY_VERSION );
		}
	}

	/**
	 * Studio design tokens (builder.css) on Arkiv-import without loading React.
	 */
	public static function enqueue_admin_arkiv_styles(): void {
		$dir = TIMELINE_HISTORY_DIR . 'assets/dist/';
		$url = TIMELINE_HISTORY_URL . 'assets/dist/';
		if ( file_exists( $dir . 'builder.css' ) ) {
			wp_enqueue_style( 'timeline-history-builder', $url . 'builder.css', array(), TIMELINE_HISTORY_VERSION );
		}
	}

	public static function enqueue_admin_builder(): void {
		if ( self::$builder_enqueued ) {
			return;
		}

		$dir = TIMELINE_HISTORY_DIR . 'assets/dist/';
		$url = TIMELINE_HISTORY_URL . 'assets/dist/';

		if ( ! file_exists( $dir . 'builder.js' ) ) {
			return;
		}

		self::$builder_enqueued = true;

		// WP media modal (upload / library picker) for Studio story images.
		wp_enqueue_media();

		wp_enqueue_style( 'timeline-history-builder', $url . 'builder.css', array(), TIMELINE_HISTORY_VERSION );
		wp_enqueue_style( 'timeline-history-view', $url . 'timeline.css', array(), TIMELINE_HISTORY_VERSION );

		$builder_asset = self::script_asset( 'builder' );
		$builder_deps  = array_merge( array( 'jquery' ), $builder_asset['dependencies'] );
		$builder_deps  = array_values( array_unique( $builder_deps ) );

		wp_enqueue_script(
			'timeline-history-builder',
			$url . 'builder.js',
			$builder_deps,
			$builder_asset['version'],
			true
		);

		wp_localize_script(
			'timeline-history-builder',
			'timelineHistoryAdmin',
			array(
				'restBase'      => esc_url_raw( rest_url( 'timeline-history/v1' ) ),
				'restUrl'       => esc_url_raw( rest_url( 'timeline-history/v1/preview' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'defaultConfig' => Timeline_History_Settings::to_config(),
				'timelines'     => Timeline_History_Sets::list_all(),
			)
		);

		$mount = <<<'JS'
(function () {
	function fail(msg) {
		var root = document.getElementById('timeline-history-builder-root');
		if (root) {
			root.innerHTML = '<p style="padding:1.25rem;color:#b32d2e;margin:0;">' + msg + '</p>';
		}
		console.error('[Historical Timeline]', msg, {
			TimelineHistoryBuilder: window.TimelineHistoryBuilder,
			timelineHistoryAdmin: window.timelineHistoryAdmin
		});
	}
	function urlTimelineSlug() {
		try {
			return new URL(window.location.href).searchParams.get('timeline') || '';
		} catch (e) {
			return '';
		}
	}

	function boot() {
		var root = document.getElementById('timeline-history-builder-root');
		var admin = window.timelineHistoryAdmin || {};
		var api = window.TimelineHistoryBuilder;
		var mountFn = api && api.mountAdminBuilder;
		var timelineSlug = urlTimelineSlug();
		if (!root) {
			return;
		}
		if (typeof mountFn !== 'function') {
			fail('Timeline Builder JS loadede ikke (mountAdminBuilder mangler). Genupload plugin-zip.');
			return;
		}
		var cfg = admin.defaultConfig;
		var restBase = admin.restBase || '';
		var run = function (config, stories, timelines) {
			try {
				mountFn(root, {
					config: config || cfg,
					stories: stories || [],
					timelines: timelines || admin.timelines || [],
					initialTimelineSlug: timelineSlug,
					restBase: restBase,
					nonce: admin.nonce || ''
				});
			} catch (err) {
				fail('Timeline Builder crashede under mount: ' + (err && err.message ? err.message : String(err)));
			}
		};
		var previewUrl = admin.restUrl || (restBase ? restBase.replace(/\/$/, '') + '/preview' : '');
		if (timelineSlug) {
			previewUrl += (previewUrl.indexOf('?') >= 0 ? '&' : '?') + 'timeline=' + encodeURIComponent(timelineSlug);
		}
		if (!previewUrl) {
			run(cfg, [], admin.timelines || []);
			return;
		}
		fetch(previewUrl, {
			headers: { 'X-WP-Nonce': admin.nonce || '' }
		})
			.then(function (r) {
				if (!r.ok) throw new Error('REST ' + r.status);
				return r.json();
			})
			.then(function (data) {
				run(data.config || cfg, data.stories || [], data.timelines || admin.timelines || []);
			})
			.catch(function (err) {
				console.warn('[Historical Timeline] REST preview failed, using defaults', err);
				run(cfg, [], admin.timelines || []);
			});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
JS;
		wp_add_inline_script( 'timeline-history-builder', $mount, 'after' );
	}
}
