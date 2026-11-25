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
					'name'          => __( 'Historier', 'prix-historical-timeline' ),
					'singular_name' => __( 'Historie', 'prix-historical-timeline' ),
					'add_new_item'  => __( 'Tilføj historie', 'prix-historical-timeline' ),
					'edit_item'     => __( 'Rediger historie', 'prix-historical-timeline' ),
					'all_items'     => __( 'Alle historier', 'prix-historical-timeline' ),
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
					'name'          => __( 'Timeline Categories', 'prix-historical-timeline' ),
					'singular_name' => __( 'Timeline Category', 'prix-historical-timeline' ),
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
					'name'          => __( 'Tidslinjer', 'prix-historical-timeline' ),
					'singular_name' => __( 'Tidslinje', 'prix-historical-timeline' ),
					'search_items'  => __( 'Søg tidslinjer', 'prix-historical-timeline' ),
					'all_items'     => __( 'Alle tidslinjer', 'prix-historical-timeline' ),
					'edit_item'     => __( 'Rediger tidslinje', 'prix-historical-timeline' ),
					'update_item'   => __( 'Opdater tidslinje', 'prix-historical-timeline' ),
					'add_new_item'  => __( 'Tilføj tidslinje', 'prix-historical-timeline' ),
					'new_item_name' => __( 'Nyt tidslinjenavn', 'prix-historical-timeline' ),
					'menu_name'     => __( 'Tidslinjer', 'prix-historical-timeline' ),
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
