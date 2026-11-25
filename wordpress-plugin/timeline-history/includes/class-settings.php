<?php
/**
 * Global plugin settings.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Settings {

	public const OPTION_KEY        = 'timeline_history_settings';
	public const ADMIN_MENU_SLUG   = 'timeline-history-arkiv';
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'timeline_sets_menu' ), 13 );
		add_action( 'admin_menu', array( $this, 'normalize_admin_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Custom SVG for wp-admin (20×20).
	 */
	public static function admin_menu_icon(): string {
		// Arkiv: farvet A + tidslinje (matcher tidslinjens accent/linje på fronten).
		$accent = '#e8925f';
		$line   = '#5a8f75';
		$svg    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
			. '<path d="M5.25 12.75L10 4.25l4.75 8.5" stroke="' . $accent . '" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>'
			. '<path d="M7.1 9.75h5.8" stroke="' . $accent . '" stroke-width="1.2" stroke-linecap="round"/>'
			. '<path d="M3.5 15.75h13" stroke="' . $line . '" stroke-width="1.2" stroke-linecap="round"/>'
			. '<circle cx="5" cy="15.75" r="1.35" fill="' . $accent . '"/>'
			. '<circle cx="10" cy="15.75" r="1.35" fill="' . $line . '"/>'
			. '<circle cx="15" cy="15.75" r="1.35" fill="' . $accent . '"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public function timeline_sets_menu(): void {
		add_submenu_page(
			self::ADMIN_MENU_SLUG,
			__( 'Tidslinjer', 'prix-historical-timeline' ),
			__( 'Tidslinjer', 'prix-historical-timeline' ),
			'edit_posts',
			'edit-tags.php?taxonomy=' . Timeline_History_Sets::TAXONOMY . '&post_type=timeline_story'
		);
	}

	/**
	 * Første punkt = Import, ingen CPT-liste, fast rækkefølge.
	 */
	public function normalize_admin_menu(): void {
		global $submenu;

		$parent = self::ADMIN_MENU_SLUG;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		remove_submenu_page( $parent, 'edit.php?post_type=timeline_story' );

		$import_label = __( 'Import fra Arkiv.dk', 'prix-historical-timeline' );
		if ( isset( $submenu[ $parent ][0][0] ) ) {
			$submenu[ $parent ][0][0] = $import_label;
		}

		$order = array(
			self::ADMIN_MENU_SLUG,
			'timeline-history-builder',
			'edit-tags.php?taxonomy=' . Timeline_History_Sets::TAXONOMY . '&post_type=timeline_story',
		);

		$by_slug = array();
		foreach ( $submenu[ $parent ] as $item ) {
			if ( ! isset( $item[2] ) ) {
				continue;
			}
			$by_slug[ (string) $item[2] ] = $item;
		}

		$sorted = array();
		foreach ( $order as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$sorted[] = $by_slug[ $slug ];
				unset( $by_slug[ $slug ] );
			}
		}
		foreach ( $by_slug as $item ) {
			$sorted[] = $item;
		}

		$submenu[ $parent ] = array_values( $sorted );
	}

	public function register(): void {
		register_setting(
			'timeline_history',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function defaults(): array {
		return array(
			'layout'      => 'vertical',
			'theme'       => 'card',
			'skin'        => 'light',
			'animation'   => 'fade-in',
			'show_media'  => true,
			'date_format' => 'd. MMM yyyy',
			'max_stories' => 0,
			'line_style'  => 'solid',
			'theme_adapt' => 'auto',
		);
	}

	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();
		$out['layout']      = 'vertical';
		$out['theme']       = in_array(
			$input['theme'] ?? '',
			array( 'minimal', 'card', 'editorial', 'archive', 'modern', 'heritage' ),
			true
		) ? $input['theme'] : $out['theme'];
		$out['skin']        = in_array( $input['skin'] ?? '', array( 'light', 'dark' ), true ) ? $input['skin'] : $out['skin'];
		$out['animation']   = in_array( $input['animation'] ?? '', array( 'none', 'fade-in' ), true ) ? $input['animation'] : $out['animation'];
		$out['show_media']  = true;
		$out['date_format'] = sanitize_text_field( $input['date_format'] ?? $out['date_format'] );
		$out['max_stories'] = absint( $input['max_stories'] ?? 0 );
		$out['line_style']  = in_array( $input['line_style'] ?? '', array( 'solid', 'dashed', 'gradient' ), true ) ? $input['line_style'] : $out['line_style'];
		$out['theme_adapt'] = in_array( $input['theme_adapt'] ?? '', array( 'auto', 'off' ), true ) ? $input['theme_adapt'] : $out['theme_adapt'];
		if ( isset( $input['tokens'] ) && is_array( $input['tokens'] ) ) {
			$out['tokens'] = $input['tokens'];
		}
		return $out;
	}

	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	public static function to_config( array $overrides = array() ): array {
		$s = wp_parse_args( $overrides, self::get() );
		return array(
			'layout'        => $s['layout'],
			'theme'         => $s['theme'],
			'skin'          => $s['skin'],
			'animation'     => $s['animation'],
			'showMedia'     => (bool) $s['show_media'],
			'dateFormat'    => $s['date_format'],
			'categorySlugs' => isset( $s['category_slugs'] ) && is_array( $s['category_slugs'] ) ? $s['category_slugs'] : array(),
			'maxStories'    => (int) $s['max_stories'],
			'lineStyle'     => $s['line_style'],
			'themeAdapt'    => in_array( $s['theme_adapt'] ?? '', array( 'auto', 'off' ), true ) ? $s['theme_adapt'] : 'auto',
			'tokens'        => isset( $s['tokens'] ) && is_array( $s['tokens'] ) ? $s['tokens'] : array(),
		);
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Historical Timeline – standardindstillinger', 'prix-historical-timeline' ); ?></h1>

			<p class="description" style="margin:8px 0 16px;">
				<?php esc_html_e( 'Shortcode til sider:', 'prix-historical-timeline' ); ?>
				<code>[timeline_history timeline="slug"]</code>
				-
				<?php esc_html_e( 'eller blokken Historical Timeline. I Studio: kopiér shortcode under den aktive tidslinje; visning via tandhjulet.', 'prix-historical-timeline' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'timeline_history' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Default layout', 'prix-historical-timeline' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[layout]">
								<option value="vertical" <?php selected( $s['layout'], 'vertical' ); ?>><?php esc_html_e( 'Vertical', 'prix-historical-timeline' ); ?></option>
								<option value="horizontal" <?php selected( $s['layout'], 'horizontal' ); ?>><?php esc_html_e( 'Horizontal', 'prix-historical-timeline' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default theme', 'prix-historical-timeline' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[theme]">
								<?php foreach ( array( 'minimal', 'card', 'editorial' ) as $theme ) : ?>
									<option value="<?php echo esc_attr( $theme ); ?>" <?php selected( $s['theme'], $theme ); ?>><?php echo esc_html( $theme ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=timeline-history-builder' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Åbn Studio', 'prix-historical-timeline' ); ?></a></p>
		</div>
		<?php
	}
}
