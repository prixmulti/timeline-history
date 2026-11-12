<?php
/**
 * Timeline Builder admin page (React).
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Admin_Builder {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_submenu_page(
			Timeline_History_Settings::ADMIN_MENU_SLUG,
			__( 'Timeline Studio', 'timeline-history' ),
			__( 'Studio', 'timeline-history' ),
			'edit_posts',
			'timeline-history-builder',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( ! $this->is_builder_screen( $hook ) ) {
			return;
		}
		Timeline_History_Assets::enqueue_admin_builder();
	}

	/**
	 * Match Builder page by query arg and/or screen hook (hook names vary by WP/menu).
	 */
	private function is_builder_screen( string $hook = '' ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin page check.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'timeline-history-builder' === $page ) {
			return true;
		}
		return false !== strpos( $hook, 'timeline-history-builder' );
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		Timeline_History_Assets::enqueue_admin_builder();

		$asset_ok = file_exists( TIMELINE_HISTORY_DIR . 'assets/dist/builder.js' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$imported = isset( $_GET['imported'] ) && '1' === $_GET['imported'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$imp_created = $imported && isset( $_GET['created'] ) ? (int) $_GET['created'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$imp_updated = $imported && isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$imp_skipped = $imported && isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0;
		?>
		<style>
			.th-studio-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:0.35rem;}
			.th-studio-header h1{margin:0;}
			.th-studio-gear-wrap{position:relative;flex-shrink:0;margin-top:4px;}
			.th-studio-gear{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;padding:0;border:1px solid #c3c4c7;border-radius:8px;background:#fff;cursor:pointer;color:#1d2327;box-shadow:0 1px 0 rgba(0,0,0,.04);}
			.th-studio-gear:hover,.th-studio-gear[aria-expanded="true"]{border-color:#3d5a4c;color:#3d5a4c;background:#f3f7f4;}
			.th-studio-gear .dashicons{width:20px;height:20px;font-size:20px;line-height:1;}
			.th-studio-popover{display:none;position:absolute;top:calc(100% + 8px);right:0;z-index:100000;width:min(520px,calc(100vw - 48px));max-height:none;overflow:visible;padding:0;background:#fff;border:1px solid #c3c4c7;border-radius:12px;box-shadow:0 16px 48px rgba(0,0,0,.14);}
			.th-studio-popover.is-open{display:block;}
			@media (max-height:640px){
				.th-studio-popover{max-height:calc(100vh - 96px);overflow:auto;}
			}
		</style>
		<div class="wrap">
			<?php if ( $imported ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: created, 2: updated, 3: skipped */
								__( 'Import færdig: %1$d oprettet, %2$d opdateret, %3$d sprunget over. Tidslinjen er valgt herunder.', 'timeline-history' ),
								$imp_created,
								$imp_updated,
								$imp_skipped
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<div class="th-studio-header">
				<div>
					<h1><?php esc_html_e( 'Timeline Studio', 'timeline-history' ); ?></h1>
				</div>
				<div class="th-studio-gear-wrap">
					<button
						type="button"
						class="th-studio-gear"
						id="th-studio-gear"
						aria-expanded="false"
						aria-controls="th-studio-popover"
						title="<?php esc_attr_e( 'Visningsindstillinger', 'timeline-history' ); ?>"
					>
						<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Visning', 'timeline-history' ); ?></span>
					</button>
					<div class="th-studio-popover" id="th-studio-popover" role="dialog" aria-label="<?php esc_attr_e( 'Visning', 'timeline-history' ); ?>">
						<div id="th-studio-gear-mount"></div>
					</div>
				</div>
			</div>

			<?php if ( ! $asset_ok ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Mangler assets/dist/builder.js. Kør npm run pack:plugin og genupload pluginnet.', 'timeline-history' ); ?></p></div>
			<?php endif; ?>
			<div
				id="timeline-history-builder-root"
				class="th-builder-root"
				style="margin-top:1rem;min-height:480px;background:#fff;border:1px solid #dcdcde;border-radius:8px;"
			>
				<p style="padding:1.25rem;color:#646970;margin:0;"><?php esc_html_e( 'Indlæser Timeline Builder…', 'timeline-history' ); ?></p>
			</div>
		</div>
		<script>
			(function () {
				var gear = document.getElementById('th-studio-gear');
				var pop = document.getElementById('th-studio-popover');
				if (!gear || !pop) return;

				function closePop() {
					pop.classList.remove('is-open');
					gear.setAttribute('aria-expanded', 'false');
				}
				function togglePop() {
					var open = pop.classList.toggle('is-open');
					gear.setAttribute('aria-expanded', open ? 'true' : 'false');
				}

				gear.addEventListener('click', function (e) {
					e.stopPropagation();
					togglePop();
				});
				pop.addEventListener('click', function (e) {
					e.stopPropagation();
				});
				document.addEventListener('click', closePop);
				document.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') closePop();
				});
			})();
		</script>
		<?php
	}
}
