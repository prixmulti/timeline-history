<?php
/**
 * Story post meta.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Meta {

	public const DATE_KEY   = '_th_story_date';
	public const ICON_KEY   = '_th_story_icon';
	public const COLOR_KEY  = '_th_story_accent';
	public const ORDER_KEY  = '_th_story_order';

	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_timeline_story', array( $this, 'save_meta' ), 10, 2 );
	}

	public function register_meta(): void {
		register_post_meta(
			'timeline_story',
			self::DATE_KEY,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_post_meta( 'timeline_story', self::ICON_KEY, $this->string_meta_args() );
		register_post_meta( 'timeline_story', self::COLOR_KEY, $this->string_meta_args() );
		register_post_meta(
			'timeline_story',
			self::ORDER_KEY,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	private function string_meta_args(): array {
		return array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		);
	}

	public function meta_boxes(): void {
		add_meta_box(
			'th_story_details',
			__( 'Historiedetaljer', 'arkivtimeline' ),
			array( $this, 'render_box' ),
			'timeline_story',
			'side',
			'high'
		);
	}

	public function render_box( WP_Post $post ): void {
		wp_nonce_field( 'th_story_meta', 'th_story_meta_nonce' );
		$date  = get_post_meta( $post->ID, self::DATE_KEY, true );
		$icon  = get_post_meta( $post->ID, self::ICON_KEY, true );
		$color = get_post_meta( $post->ID, self::COLOR_KEY, true );
		$order = get_post_meta( $post->ID, self::ORDER_KEY, true );
		if ( empty( $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}
		?>
		<p>
			<label for="th_story_date"><strong><?php esc_html_e( 'Date', 'arkivtimeline' ); ?></strong></label>
			<input type="date" id="th_story_date" name="th_story_date" value="<?php echo esc_attr( $date ); ?>" class="widefat" />
		</p>
		<p>
			<label for="th_story_icon"><?php esc_html_e( 'Icon (emoji or character)', 'arkivtimeline' ); ?></label>
			<input type="text" id="th_story_icon" name="th_story_icon" value="<?php echo esc_attr( $icon ); ?>" class="widefat" />
		</p>
		<p>
			<label for="th_story_color"><?php esc_html_e( 'Accent color', 'arkivtimeline' ); ?></label>
			<input type="color" id="th_story_color" name="th_story_color" value="<?php echo esc_attr( $color ? $color : '#b85c38' ); ?>" />
		</p>
		<p>
			<label for="th_story_order"><?php esc_html_e( 'Custom order (optional)', 'arkivtimeline' ); ?></label>
			<input type="number" id="th_story_order" name="th_story_order" value="<?php echo esc_attr( $order ); ?>" class="widefat" />
		</p>
		<?php
	}

	public function save_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['th_story_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['th_story_meta_nonce'] ) ), 'th_story_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['th_story_date'] ) ) {
			update_post_meta( $post_id, self::DATE_KEY, sanitize_text_field( wp_unslash( $_POST['th_story_date'] ) ) );
		}
		if ( isset( $_POST['th_story_icon'] ) ) {
			update_post_meta( $post_id, self::ICON_KEY, sanitize_text_field( wp_unslash( $_POST['th_story_icon'] ) ) );
		}
		if ( isset( $_POST['th_story_color'] ) ) {
			update_post_meta( $post_id, self::COLOR_KEY, sanitize_hex_color( wp_unslash( $_POST['th_story_color'] ) ) );
		}
		if ( isset( $_POST['th_story_order'] ) && $_POST['th_story_order'] !== '' ) {
			update_post_meta( $post_id, self::ORDER_KEY, absint( $_POST['th_story_order'] ) );
		} else {
			delete_post_meta( $post_id, self::ORDER_KEY );
		}
	}
}
