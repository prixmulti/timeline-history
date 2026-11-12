<?php
/**
 * REST CRUD for timeline stories + settings save.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Stories_Controller {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register(): void {
		$ns = 'timeline-history/v1';

		register_rest_route(
			$ns,
			'/stories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_stories' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_story' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/stories/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_story' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_story' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );
					},
				),
			)
		);

		register_rest_route(
			$ns,
			'/timelines',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_timelines' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_timeline' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function list_timelines(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'timelines' => Timeline_History_Sets::list_all(),
			),
			200
		);
	}

	public function create_timeline( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$name = isset( $body['name'] ) ? (string) $body['name'] : '';
		$set  = Timeline_History_Sets::ensure( $name );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		return new WP_REST_Response( array( 'timeline' => $set ), 201 );
	}

	public function list_stories( WP_REST_Request $request ): WP_REST_Response {
		$timeline = sanitize_text_field( (string) $request->get_param( 'timeline' ) );
		return new WP_REST_Response(
			array(
				'stories'   => Timeline_History_Story_Repository::get_stories(
					array(
						'timeline' => $timeline,
					)
				),
				'timelines' => Timeline_History_Sets::list_all(),
				'config'    => Timeline_History_Settings::to_config(),
			),
			200
		);
	}

	public function create_story( WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$result = Timeline_History_Story_Repository::upsert( is_array( $body ) ? $body : array(), null );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'story' => $result ), 201 );
	}

	public function update_story( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$body   = $request->get_json_params();
		$result = Timeline_History_Story_Repository::upsert( is_array( $body ) ? $body : array(), $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'story' => $result ), 200 );
	}

	public function delete_story( WP_REST_Request $request ) {
		$result = Timeline_History_Story_Repository::delete( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	public function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$body   = $request->get_json_params();
		$config = isset( $body['config'] ) && is_array( $body['config'] ) ? $body['config'] : array();

		$mapped = array(
			'layout'      => $config['layout'] ?? '',
			'theme'       => $config['theme'] ?? '',
			'skin'        => $config['skin'] ?? '',
			'animation'   => $config['animation'] ?? '',
			'show_media'  => ! empty( $config['showMedia'] ),
			'date_format' => $config['dateFormat'] ?? '',
			'max_stories' => isset( $config['maxStories'] ) ? (int) $config['maxStories'] : 0,
			'line_style'  => $config['lineStyle'] ?? '',
		);

		if ( isset( $config['tokens'] ) && is_array( $config['tokens'] ) ) {
			$mapped['tokens'] = $config['tokens'];
		}

		$sanitized = ( new Timeline_History_Settings() )->sanitize( $mapped );
		if ( isset( $mapped['tokens'] ) ) {
			$sanitized['tokens'] = $this->sanitize_tokens( $mapped['tokens'] );
		}

		update_option( Timeline_History_Settings::OPTION_KEY, $sanitized );

		return new WP_REST_Response(
			array(
				'config' => Timeline_History_Settings::to_config(),
			),
			200
		);
	}

	/**
	 * @param mixed $tokens Raw tokens.
	 */
	private function sanitize_tokens( $tokens ): array {
		if ( ! is_array( $tokens ) ) {
			return array();
		}
		$out = array();
		if ( isset( $tokens['colors'] ) && is_array( $tokens['colors'] ) ) {
			$out['colors'] = array();
			foreach ( array( 'line', 'accent', 'cardBg', 'text', 'muted', 'pageBg' ) as $key ) {
				if ( empty( $tokens['colors'][ $key ] ) ) {
					continue;
				}
				$hex = sanitize_hex_color( (string) $tokens['colors'][ $key ] );
				if ( $hex ) {
					$out['colors'][ $key ] = $hex;
				}
			}
		}
		return $out;
	}
}
