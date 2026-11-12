<?php
/**
 * Custom post types and taxonomies.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Post_Types {

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register CPT and taxonomy. Safe to call during activation before init.
	 */
	public static function register(): void {
		register_post_type(
			'timeline_story',
			array(
				'labels'              => array(
					'name'          => __( 'Historier', 'timeline-history' ),
					'singular_name' => __( 'Historie', 'timeline-history' ),
					'add_new_item'  => __( 'Tilføj historie', 'timeline-history' ),
					'edit_item'     => __( 'Rediger historie', 'timeline-history' ),
					'all_items'     => __( 'Alle historier', 'timeline-history' ),
				),
				'public'              => true,
				'show_in_rest'        => true,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive'         => false,
				'rewrite'             => array( 'slug' => 'timeline-story' ),
				'show_in_menu'        => false,
			)
		);

		register_taxonomy(
			'timeline_category',
			array( 'timeline_story' ),
			array(
				'labels'            => array(
					'name'          => __( 'Timeline Categories', 'timeline-history' ),
					'singular_name' => __( 'Timeline Category', 'timeline-history' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'rewrite'           => array( 'slug' => 'timeline-category' ),
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			Timeline_History_Sets::TAXONOMY,
			array( 'timeline_story' ),
			array(
				'labels'            => array(
					'name'          => __( 'Tidslinjer', 'timeline-history' ),
					'singular_name' => __( 'Tidslinje', 'timeline-history' ),
					'search_items'  => __( 'Søg tidslinjer', 'timeline-history' ),
					'all_items'     => __( 'Alle tidslinjer', 'timeline-history' ),
					'edit_item'     => __( 'Rediger tidslinje', 'timeline-history' ),
					'update_item'   => __( 'Opdater tidslinje', 'timeline-history' ),
					'add_new_item'  => __( 'Tilføj tidslinje', 'timeline-history' ),
					'new_item_name' => __( 'Nyt tidslinjenavn', 'timeline-history' ),
					'menu_name'     => __( 'Tidslinjer', 'timeline-history' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'rewrite'           => array( 'slug' => 'tidslinje' ),
				'show_admin_column' => true,
				'show_in_menu'      => true,
			)
		);
	}
}
