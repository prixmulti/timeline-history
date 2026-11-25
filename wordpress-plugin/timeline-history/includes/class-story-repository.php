<?php
/**
 * Query stories for frontend and REST.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Story_Repository {

	/**
	 * @param array $args category, limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_stories( array $args = array() ): array {
		$defaults = array(
			'category_slugs' => array(),
			'timeline'       => '',
			'limit'          => 0,
			'post__in'       => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'      => 'timeline_story',
			'post_status'    => 'publish',
			'posts_per_page' => $args['limit'] > 0 ? $args['limit'] : -1,
			'orderby'        => 'meta_value',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- timeline stories ordered by story date meta.
			'meta_key'       => Timeline_History_Meta::DATE_KEY,
			'order'          => 'ASC',
		);

		if ( ! empty( $args['post__in'] ) ) {
			$query_args['post__in'] = array_map( 'absint', $args['post__in'] );
			$query_args['orderby']  = 'post__in';
		}

		$tax_query = array();
		if ( ! empty( $args['category_slugs'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'timeline_category',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', $args['category_slugs'] ),
			);
		}
		if ( ! empty( $args['timeline'] ) ) {
			$set = Timeline_History_Sets::resolve( (string) $args['timeline'] );
			if ( $set ) {
				$tax_query[] = array(
					'taxonomy' => Timeline_History_Sets::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( $set['slug'] ),
				);
			} else {
				return array();
			}
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( ! empty( $tax_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filter stories by category/timeline set.
			$query_args['tax_query'] = $tax_query;
		}

		$q = new WP_Query( $query_args );
		$out = array();

		foreach ( $q->posts as $post ) {
			$out[] = self::map_post( $post );
		}

		wp_reset_postdata();
		return $out;
	}

	public static function map_post( WP_Post $post ): array {
		$date = get_post_meta( $post->ID, Timeline_History_Meta::DATE_KEY, true );
		if ( empty( $date ) ) {
			$date = get_the_date( 'Y-m-d', $post );
		}
		$thumb  = get_the_post_thumbnail_url( $post, 'large' );
		$order  = get_post_meta( $post->ID, Timeline_History_Meta::ORDER_KEY, true );
		$gallery = get_post_meta( $post->ID, '_th_story_images', true );
		$images  = array();
		if ( is_array( $gallery ) ) {
			foreach ( $gallery as $url ) {
				$url = esc_url_raw( (string) $url );
				if ( $url ) {
					$images[] = $url;
				}
			}
		}
		if ( empty( $images ) && $thumb ) {
			$images[] = $thumb;
		}

		$title_raw   = get_the_title( $post );
		$excerpt_raw = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
		// Decode leftover HTML entities from arkiv.dk imports (&#8211;, &#xD;, …).
		$title   = class_exists( 'Timeline_History_Arkiv_Client' )
			? Timeline_History_Arkiv_Client::decode_text( (string) $title_raw )
			: (string) $title_raw;
		$excerpt = class_exists( 'Timeline_History_Arkiv_Client' )
			? Timeline_History_Arkiv_Client::decode_text( (string) $excerpt_raw )
			: (string) $excerpt_raw;

		$source = '';
		if ( class_exists( 'Timeline_History_Arkiv_Client' ) ) {
			$source = (string) get_post_meta( $post->ID, Timeline_History_Arkiv_Client::SOURCE_URL_KEY, true );
		}

		$item = array(
			'id'          => (string) $post->ID,
			'date'        => $date,
			'title'       => $title,
			'excerpt'     => $excerpt,
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core content filter.
			'content'     => apply_filters( 'the_content', $post->post_content ),
			'images'      => $images,
			'image'       => ! empty( $images[0] ) ? $images[0] : '',
			'icon'        => (string) get_post_meta( $post->ID, Timeline_History_Meta::ICON_KEY, true ),
			'accentColor' => (string) get_post_meta( $post->ID, Timeline_History_Meta::COLOR_KEY, true ),
		);

		if ( $source !== '' ) {
			$item['sourceUrl'] = esc_url_raw( $source );
		}

		if ( class_exists( 'Timeline_History_Arkiv_Client' ) ) {
			$bemaerkning_raw = (string) get_post_meta( $post->ID, Timeline_History_Arkiv_Client::BEMAERKNING_KEY, true );
			if ( $bemaerkning_raw !== '' ) {
				$item['bemaerkning'] = Timeline_History_Arkiv_Client::decode_text( $bemaerkning_raw );
			}

			$caption_meta = get_post_meta( $post->ID, Timeline_History_Arkiv_Client::IMAGE_CAPTIONS_KEY, true );
			if ( is_array( $caption_meta ) && ! empty( $caption_meta ) ) {
				$captions = array();
				foreach ( $caption_meta as $cap ) {
					$cap = Timeline_History_Arkiv_Client::decode_text( (string) $cap );
					$captions[] = $cap;
				}
				$item['imageCaptions'] = $captions;
			}
		}

		if ( $order !== '' && $order !== false ) {
			$item['order'] = (int) $order;
		}

		return $item;
	}

	/**
	 * Create or update a timeline story from Studio payload.
	 *
	 * @param array    $data Story fields (title, date, excerpt, images, icon, accentColor, order).
	 * @param int|null $id   Existing post ID or null to create.
	 * @return array|\WP_Error Mapped story or error.
	 */
	public static function upsert( array $data, ?int $id = null ) {
		$title_in = isset( $data['title'] ) ? (string) $data['title'] : '';
		if ( class_exists( 'Timeline_History_Arkiv_Client' ) ) {
			$title_in = Timeline_History_Arkiv_Client::decode_text( $title_in );
		}
		$title = sanitize_text_field( $title_in );
		if ( '' === $title ) {
			return new WP_Error( 'th_missing_title', __( 'Titel er påkrævet.', 'prix-historical-timeline' ), array( 'status' => 400 ) );
		}

		$excerpt_in = isset( $data['excerpt'] ) ? (string) $data['excerpt'] : '';
		if ( isset( $data['content'] ) && $excerpt_in === '' ) {
			$excerpt_in = wp_strip_all_tags( (string) $data['content'] );
		}
		if ( class_exists( 'Timeline_History_Arkiv_Client' ) ) {
			$excerpt_in = Timeline_History_Arkiv_Client::decode_text( $excerpt_in );
		}
		$excerpt = sanitize_textarea_field( $excerpt_in );

		$postarr = array(
			'post_type'    => 'timeline_story',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $excerpt,
			'post_excerpt' => $excerpt,
		);

		if ( $id ) {
			$existing = get_post( $id );
			if ( ! $existing || 'timeline_story' !== $existing->post_type ) {
				return new WP_Error( 'th_not_found', __( 'Historie findes ikke.', 'prix-historical-timeline' ), array( 'status' => 404 ) );
			}
			$postarr['ID'] = $id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result;
		$date    = isset( $data['date'] ) ? sanitize_text_field( (string) $data['date'] ) : gmdate( 'Y-m-d' );
		update_post_meta( $post_id, Timeline_History_Meta::DATE_KEY, $date );

		$icon = isset( $data['icon'] ) ? sanitize_text_field( (string) $data['icon'] ) : '';
		if ( $icon !== '' ) {
			update_post_meta( $post_id, Timeline_History_Meta::ICON_KEY, $icon );
		} else {
			delete_post_meta( $post_id, Timeline_History_Meta::ICON_KEY );
		}

		$color_raw = isset( $data['accentColor'] ) ? (string) $data['accentColor'] : '';
		$color     = $color_raw !== '' ? sanitize_hex_color( $color_raw ) : '';
		if ( $color ) {
			update_post_meta( $post_id, Timeline_History_Meta::COLOR_KEY, $color );
		} else {
			delete_post_meta( $post_id, Timeline_History_Meta::COLOR_KEY );
		}

		if ( isset( $data['order'] ) && $data['order'] !== '' && $data['order'] !== null ) {
			update_post_meta( $post_id, Timeline_History_Meta::ORDER_KEY, absint( $data['order'] ) );
		}

		$images = array();
		if ( isset( $data['images'] ) && is_array( $data['images'] ) ) {
			foreach ( $data['images'] as $url ) {
				$url = esc_url_raw( (string) $url );
				if ( $url ) {
					$images[] = $url;
				}
			}
		} elseif ( ! empty( $data['image'] ) ) {
			$url = esc_url_raw( (string) $data['image'] );
			if ( $url ) {
				$images[] = $url;
			}
		}
		update_post_meta( $post_id, '_th_story_images', $images );

		if ( array_key_exists( 'imageCaptions', $data ) && is_array( $data['imageCaptions'] ) ) {
			$caps = array();
			foreach ( $data['imageCaptions'] as $cap ) {
				$cap_in = class_exists( 'Timeline_History_Arkiv_Client' )
					? Timeline_History_Arkiv_Client::decode_text( (string) $cap )
					: (string) $cap;
				$caps[] = sanitize_textarea_field( $cap_in );
			}
			while ( count( $caps ) < count( $images ) ) {
				$caps[] = '';
			}
			$caps = array_slice( $caps, 0, count( $images ) );
			$has_any = (bool) array_filter(
				$caps,
				static function ( $s ) {
					return trim( (string) $s ) !== '';
				}
			);
			if ( $has_any && class_exists( 'Timeline_History_Arkiv_Client' ) ) {
				update_post_meta( $post_id, Timeline_History_Arkiv_Client::IMAGE_CAPTIONS_KEY, $caps );
			} elseif ( class_exists( 'Timeline_History_Arkiv_Client' ) ) {
				delete_post_meta( $post_id, Timeline_History_Arkiv_Client::IMAGE_CAPTIONS_KEY );
			}
		}

		if ( ! empty( $data['timeline'] ) ) {
			Timeline_History_Sets::assign( $post_id, (string) $data['timeline'] );
		}

		if ( array_key_exists( 'bemaerkning', $data ) && class_exists( 'Timeline_History_Arkiv_Client' ) ) {
			$bem_in = Timeline_History_Arkiv_Client::decode_text( (string) $data['bemaerkning'] );
			$bem    = sanitize_textarea_field( $bem_in );
			if ( $bem !== '' ) {
				update_post_meta( $post_id, Timeline_History_Arkiv_Client::BEMAERKNING_KEY, $bem );
			} else {
				delete_post_meta( $post_id, Timeline_History_Arkiv_Client::BEMAERKNING_KEY );
			}
		}

		$post = get_post( $post_id );
		return self::map_post( $post );
	}

	/**
	 * Permanently delete a timeline story.
	 *
	 * @return true|\WP_Error
	 */
	public static function delete( int $id ) {
		$post = get_post( $id );
		if ( ! $post || 'timeline_story' !== $post->post_type ) {
			return new WP_Error( 'th_not_found', __( 'Historie findes ikke.', 'prix-historical-timeline' ), array( 'status' => 404 ) );
		}
		$deleted = wp_delete_post( $id, true );
		if ( ! $deleted ) {
			return new WP_Error( 'th_delete_failed', __( 'Kunne ikke slette historien.', 'prix-historical-timeline' ), array( 'status' => 500 ) );
		}
		return true;
	}
}
