<?php
/**
 * Named timelines (taxonomy timeline_set).
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Sets {

	public const TAXONOMY       = 'timeline_set';
	public const META_DISPLAY   = '_th_timeline_display';

	public static function boot(): void {
		add_action( 'delete_' . self::TAXONOMY, array( __CLASS__, 'on_term_deleted' ), 10, 4 );
	}

	/**
	 * When a tidslinje (taxonomy term) is removed, delete all attached historier permanently.
	 *
	 * @param int                  $term         Term ID.
	 * @param int                  $tt_id        Term taxonomy ID.
	 * @param \WP_Term             $deleted_term Deleted term object.
	 * @param array<int, int>|null $object_ids   Post IDs linked to the term before deletion.
	 */
	public static function on_term_deleted( $term, $tt_id, $deleted_term, $object_ids = null ): void {
		unset( $tt_id, $deleted_term );

		$term_id = (int) $term;
		if ( $term_id <= 0 ) {
			return;
		}

		$post_ids = array();
		if ( is_array( $object_ids ) && ! empty( $object_ids ) ) {
			$post_ids = array_map( 'absint', $object_ids );
		} else {
			$post_ids = get_posts(
				array(
					'post_type'      => 'timeline_story',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					'tax_query'      => array(
						array(
							'taxonomy' => self::TAXONOMY,
							'field'    => 'term_id',
							'terms'    => array( $term_id ),
						),
					),
				)
			);
		}

		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
		foreach ( $post_ids as $post_id ) {
			if ( ! class_exists( 'Timeline_History_Story_Repository' ) ) {
				wp_delete_post( (int) $post_id, true );
				continue;
			}
			$result = Timeline_History_Story_Repository::delete( (int) $post_id );
			if ( is_wp_error( $result ) ) {
				wp_delete_post( (int) $post_id, true );
			}
		}
	}

	/**
	 * Ensure a named timeline exists; return term data.
	 *
	 * @return array{id:int,name:string,slug:string}|\WP_Error
	 */
	public static function ensure( string $name ) {
		$name = sanitize_text_field( trim( $name ) );
		if ( $name === '' ) {
			return new WP_Error( 'th_timeline_name', __( 'Tidslinjen skal have et navn.', 'timeline-history' ) );
		}

		$existing = term_exists( $name, self::TAXONOMY );
		if ( $existing ) {
			$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
			$term    = get_term( $term_id, self::TAXONOMY );
			if ( $term && ! is_wp_error( $term ) ) {
				return self::map_term( $term );
			}
		}

		$created = wp_insert_term( $name, self::TAXONOMY );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$term = get_term( (int) $created['term_id'], self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'th_timeline_create', __( 'Kunne ikke oprette tidslinjen.', 'timeline-history' ) );
		}

		return self::map_term( $term );
	}

	/**
	 * Resolve slug or name to term.
	 *
	 * @return array{id:int,name:string,slug:string}|null
	 */
	public static function resolve( string $slug_or_name ) {
		$slug_or_name = trim( $slug_or_name );
		if ( $slug_or_name === '' ) {
			return null;
		}

		$term = get_term_by( 'slug', sanitize_title( $slug_or_name ), self::TAXONOMY );
		if ( ! $term ) {
			$term = get_term_by( 'name', $slug_or_name, self::TAXONOMY );
		}
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		return self::map_term( $term );
	}

	/**
	 * @return array<int, array{id:int,name:string,slug:string,count:int}>
	 */
	public static function list_all(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $term ) {
			$mapped          = self::map_term( $term );
			$mapped['count'] = (int) $term->count;
			$out[]           = $mapped;
		}
		return $out;
	}

	/**
	 * Stored display settings for one timeline (PHP option shape).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_display_settings( string $slug ): array {
		$set = self::resolve( $slug );
		if ( ! $set ) {
			return Timeline_History_Settings::defaults();
		}
		$stored = get_term_meta( (int) $set['id'], self::META_DISPLAY, true );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return wp_parse_args( $stored, Timeline_History_Settings::defaults() );
		}
		// Existing timelines without saved meta: keep current global look once.
		return Timeline_History_Settings::get();
	}

	/**
	 * @param array<string, mixed> $sanitized Timeline_History_Settings::sanitize() output.
	 */
	public static function save_display_settings( int $term_id, array $sanitized ): void {
		if ( $term_id <= 0 ) {
			return;
		}
		update_term_meta( $term_id, self::META_DISPLAY, $sanitized );
	}

	/**
	 * Frontend / Studio config for a named timeline.
	 *
	 * @param array<string, mixed> $overrides Shortcode/block overrides (layout, theme, …).
	 */
	public static function config_for_timeline( string $slug, array $overrides = array() ): array {
		$php = wp_parse_args( $overrides, self::get_display_settings( $slug ) );
		return Timeline_History_Settings::to_config( $php );
	}

	public static function assign( int $post_id, string $slug_or_name ): bool {
		$set = self::resolve( $slug_or_name );
		if ( ! $set ) {
			$ensured = self::ensure( $slug_or_name );
			if ( is_wp_error( $ensured ) ) {
				return false;
			}
			$set = $ensured;
		}
		$result = wp_set_object_terms( $post_id, array( (int) $set['id'] ), self::TAXONOMY, false );
		return ! is_wp_error( $result );
	}

	/**
	 * @param \WP_Term $term Term.
	 * @return array{id:int,name:string,slug:string}
	 */
	private static function map_term( $term ): array {
		return array(
			'id'   => (int) $term->term_id,
			'name' => (string) $term->name,
			'slug' => (string) $term->slug,
		);
	}
}
