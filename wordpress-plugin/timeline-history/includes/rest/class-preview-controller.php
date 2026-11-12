<?php
/**
 * REST preview endpoint.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Preview_Controller {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register(): void {
		register_rest_route(
			'timeline-history/v1',
			'/preview',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_preview' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'post_preview' ),
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
			)
		);
	}

	public function get_preview( WP_REST_Request $request ): WP_REST_Response {
		$config   = Timeline_History_Settings::to_config();
		$timeline = sanitize_text_field( (string) $request->get_param( 'timeline' ) );
		$stories  = Timeline_History_Story_Repository::get_stories(
			array(
				'category_slugs' => $config['categorySlugs'],
				'timeline'       => $timeline,
				'limit'          => (int) $config['maxStories'],
			)
		);

		return new WP_REST_Response(
			array(
				'config'    => $config,
				'stories'   => $stories,
				'timelines' => Timeline_History_Sets::list_all(),
				'timeline'  => $timeline,
			),
			200
		);
	}

	public function post_preview( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$config_overrides = isset( $body['config'] ) && is_array( $body['config'] ) ? $body['config'] : array();

		$php_overrides = array();
		if ( isset( $config_overrides['layout'] ) ) {
			$php_overrides['layout'] = sanitize_text_field( $config_overrides['layout'] );
		}
		if ( isset( $config_overrides['theme'] ) ) {
			$php_overrides['theme'] = sanitize_text_field( $config_overrides['theme'] );
		}

		$config = Timeline_History_Settings::to_config( $php_overrides );

		$category = isset( $body['categorySlugs'] ) && is_array( $body['categorySlugs'] )
			? array_map( 'sanitize_title', $body['categorySlugs'] )
			: $config['categorySlugs'];

		$stories = Timeline_History_Story_Repository::get_stories(
			array(
				'category_slugs' => $category,
				'limit'          => isset( $config_overrides['maxStories'] ) ? (int) $config_overrides['maxStories'] : (int) $config['maxStories'],
			)
		);

		return new WP_REST_Response(
			array(
				'config'  => $config,
				'stories' => $stories,
			),
			200
		);
	}
}
