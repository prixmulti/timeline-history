<?php
/**
 * Admin: import timeline stories from arkiv.dk search URLs.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Admin_Arkiv_Import {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_reset_session' ) );
		add_action( 'admin_head', array( $this, 'print_persistent_busy_head' ), 1 );
		add_action( 'wp_ajax_th_arkiv_search_archives', array( $this, 'ajax_search_archives' ) );
		add_action( 'wp_ajax_th_arkiv_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_th_arkiv_import', array( $this, 'ajax_import' ) );
	}

	/**
	 * Full-screen busy state across form POST / redirect (sessionStorage + early html class).
	 */
	public function print_persistent_busy_head(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['page'] ) || 'timeline-history-arkiv' !== $_GET['page'] ) {
			return;
		}
		?>
		<script>
			try {
				if ( sessionStorage.getItem( 'th_arkiv_nav_busy' ) === '1' ) {
					document.documentElement.classList.add( 'th-arkiv-nav-busy' );
				}
			} catch ( e ) {}
		</script>
		<style id="th-arkiv-nav-busy-css">
			html.th-arkiv-nav-busy #th-arkiv-busy-bootstrap,
			html.th-arkiv-nav-busy #th-arkiv-busy {
				display: flex !important;
			}
			html.th-arkiv-nav-busy #th-arkiv-busy[hidden],
			html.th-arkiv-nav-busy #th-arkiv-busy-bootstrap[hidden] {
				display: flex !important;
			}
		</style>
		<?php
	}

	public function ajax_preview(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'timeline-history' ) ), 403 );
		}
		check_ajax_referer( 'th_arkiv_admin', 'nonce' );
		@set_time_limit( 600 );

		$resolved      = $this->resolve_posted_search();
		$url           = $resolved['url'];
		$archive_id    = $resolved['archive_id'];
		$search_string = $resolved['search_string'];
		$archive_label = $resolved['archive_label'];
		$timeline_name = isset( $_POST['th_arkiv_timeline'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['th_arkiv_timeline'] ) ) : '';
		$from          = isset( $_POST['th_arkiv_from'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_from'] ) ) : '';
		$to            = isset( $_POST['th_arkiv_to'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_to'] ) ) : '';
		$from_i        = $from !== '' ? (int) $from : null;
		$to_i          = $to !== '' ? (int) $to : null;

		if ( $url === '' ) {
			wp_send_json_error(
				array( 'message' => __( 'Vælg et lokalarkiv, eller indsæt et søgelink fra arkiv.dk under avanceret.', 'timeline-history' ) )
			);
		}

		$max_gallery      = $this->posted_max_gallery_images();
		$probe_all_counts = $this->posted_probe_all_image_counts();
		$stored           = $this->run_preview_job(
			$url,
			$from_i,
			$to_i,
			$timeline_name,
			$archive_id,
			$archive_label,
			$search_string,
			$max_gallery,
			$probe_all_counts
		);
		if ( is_wp_error( $stored ) ) {
			wp_send_json_error( array( 'message' => $stored->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'redirect' => add_query_arg(
					array(
						'page'           => 'timeline-history-arkiv',
						'th_arkiv_view'  => '1',
					),
					admin_url( 'admin.php' )
				),
				'total'    => isset( $stored['total'] ) ? (int) $stored['total'] : 0,
				'shown'    => isset( $stored['items'] ) ? count( $stored['items'] ) : 0,
			)
		);
	}

	public function ajax_import(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'timeline-history' ) ), 403 );
		}
		check_ajax_referer( 'th_arkiv_admin', 'nonce' );
		@set_time_limit( 600 );

		$resolved      = $this->resolve_posted_search();
		$url           = $resolved['url'];
		$archive_id    = $resolved['archive_id'];
		$search_string = $resolved['search_string'];
		$archive_label = $resolved['archive_label'];
		$timeline_name = isset( $_POST['th_arkiv_timeline'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['th_arkiv_timeline'] ) ) : '';
		$from          = isset( $_POST['th_arkiv_from'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_from'] ) ) : '';
		$to            = isset( $_POST['th_arkiv_to'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_to'] ) ) : '';
		$from_i        = $from !== '' ? (int) $from : null;
		$to_i          = $to !== '' ? (int) $to : null;

		$selected = isset( $_POST['th_arkiv_ids'] ) && is_array( $_POST['th_arkiv_ids'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['th_arkiv_ids'] ) )
			: array();
		$selected = array_values( array_filter( $selected ) );

		$import = $this->run_import_job( $url, $from_i, $to_i, $timeline_name, $archive_id, $archive_label, $search_string, $selected );
		if ( is_wp_error( $import ) ) {
			wp_send_json_error( array( 'message' => $import->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'redirect' => $this->studio_import_redirect_url( $import ),
			)
		);
	}

	/**
	 * @return array{items: array, total: int, matched: int, fuzzy: int, pages_fetched: int, fetch_url: string}|\WP_Error
	 */
	private function run_preview_job(
		string $url,
		?int $from_i,
		?int $to_i,
		string $timeline_name,
		int $archive_id,
		string $archive_label,
		string $search_string,
		int $max_gallery_images = 0,
		bool $probe_all_image_counts = false
	) {
		delete_transient( $this->preview_key() );
		$result = Timeline_History_Arkiv_Client::fetch_search( $url, $from_i, $to_i, 40 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result = $this->prepare_result( $result, $from_i, $to_i );
		Timeline_History_Arkiv_Client::annotate_preview_gallery_meta(
			$result['items'],
			$max_gallery_images,
			$probe_all_image_counts
		);
		set_transient(
			$this->preview_key(),
			array(
				'url'                 => $url,
				'from'                => $from_i,
				'to'                  => $to_i,
				'timeline'            => $timeline_name,
				'archive_id'          => $archive_id,
				'archive_label'       => $archive_label,
				'search'              => $search_string,
				'max_gallery_images'  => $max_gallery_images,
				'probe_image_counts'  => $probe_all_image_counts,
				'stamp'               => $this->preview_stamp( $url, $from_i, $to_i, $max_gallery_images, $probe_all_image_counts ),
				'items'               => $result['items'],
				'total'               => $result['total'],
			),
			HOUR_IN_SECONDS
		);
		return $result;
	}

	/**
	 * @param array<int, string> $selected Arkiv ids.
	 * @return array|\WP_Error
	 */
	private function run_import_job(
		string $url,
		?int $from_i,
		?int $to_i,
		string $timeline_name,
		int $archive_id,
		string $archive_label,
		string $search_string,
		array $selected
	 ) {
		if ( $timeline_name === '' ) {
			return new WP_Error( 'th_timeline', __( 'Angiv et navn til tidslinjen før import.', 'timeline-history' ) );
		}
		if ( empty( $selected ) ) {
			return new WP_Error( 'th_select', __( 'Vælg mindst én post at importere.', 'timeline-history' ) );
		}
		if ( $url === '' ) {
			return new WP_Error( 'th_url', __( 'Manglende søgelink. Vælg lokalarkiv og hent forhåndsvisning igen.', 'timeline-history' ) );
		}

		$cached = get_transient( $this->preview_key() );
		$items  = array();
		$max_gallery      = $this->posted_max_gallery_images();
		$probe_all_counts = $this->posted_probe_all_image_counts();
		$stamp            = $this->preview_stamp( $url, $from_i, $to_i, $max_gallery, $probe_all_counts );

		if ( is_array( $cached ) && ! empty( $cached['stamp'] ) && $cached['stamp'] !== $stamp ) {
			return new WP_Error(
				'th_stale',
				__( 'Søgningen er ændret siden forhåndsvisningen. Hent forhåndsvisning igen, før du importerer.', 'timeline-history' )
			);
		}

		if ( is_array( $cached ) && ! empty( $cached['items'] ) && is_array( $cached['items'] ) ) {
			$items = $cached['items'];
		} else {
			$refetch = Timeline_History_Arkiv_Client::fetch_search( $url, $from_i, $to_i, 40 );
			if ( is_wp_error( $refetch ) ) {
				return $refetch;
			}
			$refetch = $this->prepare_result( $refetch, $from_i, $to_i );
			$items   = $refetch['items'];
			set_transient(
				$this->preview_key(),
				array(
					'url'           => $url,
					'from'          => $from_i,
					'to'            => $to_i,
					'timeline'      => $timeline_name,
					'archive_id'    => $archive_id,
					'archive_label' => $archive_label,
					'search'        => $search_string,
					'stamp'              => $stamp,
					'max_gallery_images' => $max_gallery,
					'probe_image_counts' => $probe_all_counts,
					'items'              => $items,
					'total'              => $refetch['total'],
				),
				HOUR_IN_SECONDS
			);
		}

		$import_max = $max_gallery;
		if ( is_array( $cached ) && array_key_exists( 'max_gallery_images', $cached ) ) {
			$import_max = Timeline_History_Arkiv_Client::parse_max_gallery_images( $cached['max_gallery_images'] );
		}

		$selected_map = array_fill_keys( $selected, true );
		$chosen       = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = isset( $item['arkiv_id'] ) ? (string) $item['arkiv_id'] : '';
			if ( $id !== '' && isset( $selected_map[ $id ] ) ) {
				$chosen[] = $item;
			}
		}
		if ( empty( $chosen ) ) {
			return new WP_Error( 'th_chosen', __( 'De valgte poster fandtes ikke i forhåndsvisningen. Hent forhåndsvisning igen.', 'timeline-history' ) );
		}

		$import = Timeline_History_Arkiv_Client::import_items( $chosen, $timeline_name, $import_max );
		if ( is_wp_error( $import ) ) {
			return $import;
		}
		delete_transient( $this->preview_key() );
		return $import;
	}

	/**
	 * Hydrate UI from cached preview after AJAX redirect (?th_arkiv_view=1).
	 */
	private function load_view_from_transient(): ?array {
		$cached = get_transient( $this->preview_key() );
		if ( ! is_array( $cached ) || empty( $cached['items'] ) ) {
			return null;
		}
		return $cached;
	}

	/**
	 * Clear cached preview via ?th_arkiv_reset=1
	 */
	public function maybe_reset_session(): void {
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['page'] ) || 'timeline-history-arkiv' !== $_GET['page'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['th_arkiv_reset'] ) ) {
			return;
		}
		check_admin_referer( 'th_arkiv_reset' );
		delete_transient( $this->preview_key() );
		wp_safe_redirect( admin_url( 'admin.php?page=timeline-history-arkiv' ) );
		exit;
	}

	public function ajax_search_archives(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'timeline-history' ) ), 403 );
		}
		check_ajax_referer( 'th_arkiv_admin', 'nonce' );
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['term'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'results' => array(), 'total' => 0 ) );
		}
		$data = Timeline_History_Arkiv_Client::search_archives( $term, 1, 30 );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 500 );
		}
		wp_send_json_success( $data );
	}

	/**
	 * @param int|null $from Year from.
	 * @param int|null $to   Year to.
	 */
	private function preview_stamp( string $url, ?int $from, ?int $to, int $max_gallery = 0, bool $probe_all = false ): string {
		return md5( $url . '|' . ( $from ?? '' ) . '|' . ( $to ?? '' ) . '|' . $max_gallery . '|' . ( $probe_all ? '1' : '0' ) );
	}

	/**
	 * @param array{created: int, updated?: int, skipped: int, timeline?: array{slug?: string}} $import Import result.
	 */
	private function studio_import_redirect_url( array $import ): string {
		$timeline_slug = isset( $import['timeline']['slug'] ) ? sanitize_title( (string) $import['timeline']['slug'] ) : '';
		$args          = array(
			'page'     => 'timeline-history-builder',
			'imported' => '1',
			'created'  => (int) $import['created'],
			'updated'  => isset( $import['updated'] ) ? (int) $import['updated'] : 0,
			'skipped'  => (int) $import['skipped'],
		);
		if ( $timeline_slug !== '' ) {
			$args['timeline'] = $timeline_slug;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	private function posted_max_gallery_images(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST['th_arkiv_max_images'] ) ? wp_unslash( $_POST['th_arkiv_max_images'] ) : '';
		return Timeline_History_Arkiv_Client::parse_max_gallery_images( $raw );
	}

	private function posted_probe_all_image_counts(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST['th_arkiv_probe_image_counts'] ) && '1' === $_POST['th_arkiv_probe_image_counts'];
	}

	/**
	 * Resolve search URL + fields from POST.
	 *
	 * @return array{url: string, archive_id: int, search_string: string, archive_label: string}
	 */
	private function resolve_posted_search(): array {
		$archive_id    = isset( $_POST['th_arkiv_archive'] ) ? (int) $_POST['th_arkiv_archive'] : 0;
		$search_string = isset( $_POST['th_arkiv_search'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['th_arkiv_search'] ) ) : '';
		$archive_label = isset( $_POST['th_arkiv_archive_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['th_arkiv_archive_label'] ) ) : '';
		$composed_url  = Timeline_History_Arkiv_Client::compose_search_url(
			$archive_id > 0 ? $archive_id : null,
			$search_string,
			true
		);
		$url_posted = isset( $_POST['th_arkiv_url'] ) ? $this->sanitize_arkiv_url( (string) $_POST['th_arkiv_url'] ) : '';
		$url_manual = isset( $_POST['th_arkiv_url_manual'] ) && '1' === $_POST['th_arkiv_url_manual'];

		if ( $archive_id > 0 ) {
			$url = $composed_url;
		} elseif ( $url_manual && $url_posted !== '' ) {
			$url    = $url_posted;
			$parsed = Timeline_History_Arkiv_Client::parse_search_url_parts( $url );
			if ( $parsed['archive_id'] > 0 ) {
				$archive_id = $parsed['archive_id'];
			}
			if ( $parsed['searchstring'] !== '' ) {
				$search_string = $parsed['searchstring'];
			}
		} else {
			$url = $url_posted !== '' ? $url_posted : $composed_url;
		}

		return array(
			'url'           => $url,
			'archive_id'    => $archive_id,
			'search_string' => $search_string,
			'archive_label' => $archive_label,
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'timeline-history-arkiv' ) ) {
			return;
		}
		Timeline_History_Assets::enqueue_admin_arkiv_styles();
	}

	public function menu(): void {
		$slug = Timeline_History_Settings::ADMIN_MENU_SLUG;

		add_menu_page(
			__( 'ArkivTimeline', 'timeline-history' ),
			__( 'ArkivTimeline', 'timeline-history' ),
			'edit_posts',
			$slug,
			array( $this, 'render' ),
			Timeline_History_Settings::admin_menu_icon(),
			26
		);

		add_submenu_page(
			$slug,
			__( 'Import fra Arkiv.dk', 'timeline-history' ),
			__( 'Import fra Arkiv.dk', 'timeline-history' ),
			'edit_posts',
			$slug,
			array( $this, 'render' )
		);
	}

	private function preview_key(): string {
		return 'th_arkiv_preview_' . get_current_user_id();
	}

	/**
	 * Keep query string intact (esc_url_raw can be too aggressive on some hosts).
	 */
	private function sanitize_arkiv_url( string $url ): string {
		$url = trim( wp_unslash( $url ) );
		$url = esc_url_raw( $url );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Ensure year_fit on cached items and sort for display.
	 *
	 * @param array    $result Fetch result.
	 * @param int|null $from   Year from.
	 * @param int|null $to     Year to.
	 * @return array
	 */
	private function prepare_result( array $result, ?int $from, ?int $to ): array {
		$items   = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
		$matched = 0;
		$fuzzy   = 0;
		foreach ( $items as &$item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$fit              = Timeline_History_Arkiv_Client::year_fit( $item, $from, $to );
			$item['year_fit'] = $fit;
			if ( 'match' === $fit ) {
				$matched++;
			} elseif ( 'fuzzy' === $fit || 'unknown' === $fit ) {
				$fuzzy++;
			}
		}
		unset( $item );

		$result['items']   = Timeline_History_Arkiv_Client::sort_items_by_year_fit( $items );
		$result['matched'] = $matched;
		$result['fuzzy']   = $fuzzy;
		return $result;
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$url           = '';
		$from          = '';
		$to            = '';
		$timeline_name = '';
		$archive_id    = 0;
		$archive_label = '';
		$search_string = '';
		$url_manual    = false;
		$result        = null;
		$import        = null;
		$error         = '';
		$import_notice = null;
		$existing_sets = Timeline_History_Sets::list_all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['th_arkiv_view'] ) && '1' === $_GET['th_arkiv_view'] ) {
			$cached_view = $this->load_view_from_transient();
			if ( is_array( $cached_view ) ) {
				$url           = isset( $cached_view['url'] ) ? (string) $cached_view['url'] : '';
				$from          = isset( $cached_view['from'] ) && $cached_view['from'] ? (string) $cached_view['from'] : '';
				$to            = isset( $cached_view['to'] ) && $cached_view['to'] ? (string) $cached_view['to'] : '';
				$timeline_name = isset( $cached_view['timeline'] ) ? (string) $cached_view['timeline'] : '';
				$archive_id    = isset( $cached_view['archive_id'] ) ? (int) $cached_view['archive_id'] : 0;
				$archive_label = isset( $cached_view['archive_label'] ) ? (string) $cached_view['archive_label'] : '';
				$search_string = isset( $cached_view['search'] ) ? (string) $cached_view['search'] : '';
				$from_i        = $from !== '' ? (int) $from : null;
				$to_i          = $to !== '' ? (int) $to : null;
				$result        = $this->prepare_result(
					array(
						'items'         => $cached_view['items'],
						'total'         => isset( $cached_view['total'] ) ? (int) $cached_view['total'] : count( $cached_view['items'] ),
						'pages_fetched' => 0,
						'fetch_url'     => '',
					),
					$from_i,
					$to_i
				);
			} else {
				$error = __( 'Forhåndsvisningen findes ikke længere. Hent forhåndsvisning igen.', 'timeline-history' );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['th_arkiv_done'] ) && '1' === $_GET['th_arkiv_done'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$import_notice = array(
				'created'  => isset( $_GET['created'] ) ? (int) $_GET['created'] : 0,
				'updated'  => isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0,
				'skipped'  => isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0,
				'timeline' => isset( $_GET['timeline'] ) ? sanitize_title( wp_unslash( (string) $_GET['timeline'] ) ) : '',
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_wpnonce'] ) && ! isset( $_POST['th_arkiv_action'] ) ) {
			check_admin_referer( 'th_arkiv_import' );
			$resolved      = $this->resolve_posted_search();
			$url           = $resolved['url'];
			$archive_id    = $resolved['archive_id'];
			$search_string = $resolved['search_string'];
			$archive_label = $resolved['archive_label'];
			$url_manual    = isset( $_POST['th_arkiv_url_manual'] ) && '1' === $_POST['th_arkiv_url_manual'];
			$from          = isset( $_POST['th_arkiv_from'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_from'] ) ) : '';
			$to            = isset( $_POST['th_arkiv_to'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_to'] ) ) : '';
			$timeline_name = isset( $_POST['th_arkiv_timeline'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_timeline'] ) ) : '';
			$error         = __( 'Handlingen blev ikke sendt korrekt (prøv igen). Opdater siden hvis problemet fortsætter.', 'timeline-history' );
		} elseif ( isset( $_POST['th_arkiv_action'] ) ) {
			check_admin_referer( 'th_arkiv_import' );
			$resolved      = $this->resolve_posted_search();
			$url           = $resolved['url'];
			$archive_id    = $resolved['archive_id'];
			$search_string = $resolved['search_string'];
			$archive_label = $resolved['archive_label'];
			$url_manual    = isset( $_POST['th_arkiv_url_manual'] ) && '1' === $_POST['th_arkiv_url_manual'];
			$from          = isset( $_POST['th_arkiv_from'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_from'] ) ) : '';
			$to            = isset( $_POST['th_arkiv_to'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_to'] ) ) : '';
			$timeline_name = isset( $_POST['th_arkiv_timeline'] ) ? sanitize_text_field( wp_unslash( $_POST['th_arkiv_timeline'] ) ) : '';
			$from_i        = $from !== '' ? (int) $from : null;
			$to_i          = $to !== '' ? (int) $to : null;
			$action        = sanitize_text_field( wp_unslash( $_POST['th_arkiv_action'] ) );

			if ( 'preview' === $action ) {
				if ( $url === '' ) {
					$error = __( 'Vælg et lokalarkiv (søg efter navn), eller indsæt et søgelink fra arkiv.dk under avanceret.', 'timeline-history' );
					delete_transient( $this->preview_key() );
					$result = null;
				} else {
					$max_gallery      = $this->posted_max_gallery_images();
					$probe_all_counts = $this->posted_probe_all_image_counts();
					$stored               = $this->run_preview_job(
						$url,
						$from_i,
						$to_i,
						$timeline_name,
						$archive_id,
						$archive_label,
						$search_string,
						$max_gallery,
						$probe_all_counts
					);
					if ( is_wp_error( $stored ) ) {
						$error  = $stored->get_error_message();
						$result = null;
					} else {
						$result = $stored;
					}
				}
			}

			if ( 'import' === $action ) {
				$max_gallery        = $this->posted_max_gallery_images();
				$probe_all_counts   = $this->posted_probe_all_image_counts();
				$selected           = isset( $_POST['th_arkiv_ids'] ) && is_array( $_POST['th_arkiv_ids'] )
					? array_map( 'sanitize_text_field', wp_unslash( $_POST['th_arkiv_ids'] ) )
					: array();
				$selected = array_values( array_filter( $selected ) );

				if ( $timeline_name === '' ) {
					$error = __( 'Angiv et navn til tidslinjen før import.', 'timeline-history' );
				} elseif ( empty( $selected ) ) {
					$error = __( 'Vælg mindst én post at importere.', 'timeline-history' );
				} elseif ( $url === '' ) {
					$error = __( 'Manglende søgelink. Vælg lokalarkiv og hent forhåndsvisning igen.', 'timeline-history' );
				} else {
					$cached           = get_transient( $this->preview_key() );
					$items            = array();

					if ( is_array( $cached ) && ! empty( $cached['stamp'] ) && $cached['stamp'] !== $this->preview_stamp( $url, $from_i, $to_i, $max_gallery, $probe_all_counts ) ) {
						$error = __( 'Søgningen er ændret siden forhåndsvisningen. Hent forhåndsvisning igen, før du importerer.', 'timeline-history' );
						$cached = null;
					}

					if ( $error === '' && is_array( $cached ) && ! empty( $cached['items'] ) && is_array( $cached['items'] ) ) {
						$items = $cached['items'];
					} elseif ( $error === '' ) {
						// Fallback: fetch again from arkiv.dk using same filters.
						$refetch = Timeline_History_Arkiv_Client::fetch_search( $url, $from_i, $to_i, 40 );
						if ( is_wp_error( $refetch ) ) {
							$error = $refetch->get_error_message();
						} else {
							$refetch = $this->prepare_result( $refetch, $from_i, $to_i );
							$items   = $refetch['items'];
							set_transient(
								$this->preview_key(),
								array(
									'url'                => $url,
									'from'               => $from_i,
									'to'                 => $to_i,
									'timeline'           => $timeline_name,
									'archive_id'         => $archive_id,
									'archive_label'      => $archive_label,
									'search'             => $search_string,
									'max_gallery_images' => $max_gallery,
									'probe_image_counts' => $probe_all_counts,
									'stamp'              => $this->preview_stamp( $url, $from_i, $to_i, $max_gallery, $probe_all_counts ),
									'items'              => $items,
									'total'              => $refetch['total'],
								),
								HOUR_IN_SECONDS
							);
						}
					}

					$import_max = $max_gallery;
					if ( is_array( $cached ) && array_key_exists( 'max_gallery_images', $cached ) ) {
						$import_max = Timeline_History_Arkiv_Client::parse_max_gallery_images( $cached['max_gallery_images'] );
					}

					if ( $error === '' ) {
						$selected_map = array_fill_keys( $selected, true );
						$chosen       = array();
						foreach ( $items as $item ) {
							if ( ! is_array( $item ) ) {
								continue;
							}
							$id = isset( $item['arkiv_id'] ) ? (string) $item['arkiv_id'] : '';
							if ( $id !== '' && isset( $selected_map[ $id ] ) ) {
								$chosen[] = $item;
							}
						}

						if ( empty( $chosen ) ) {
							$error = __( 'De valgte poster fandtes ikke i forhåndsvisningen. Hent forhåndsvisning igen.', 'timeline-history' );
						} else {
							$import = Timeline_History_Arkiv_Client::import_items( $chosen, $timeline_name, $import_max );
							if ( is_wp_error( $import ) ) {
								$error  = $import->get_error_message();
								$import = null;
							} else {
								delete_transient( $this->preview_key() );
								wp_safe_redirect( $this->studio_import_redirect_url( $import ) );
								exit;
							}
						}
					}
				}

				if ( $error !== '' ) {
					$cached_retry = get_transient( $this->preview_key() );
					$retry_stamp = $this->preview_stamp( $url, $from_i, $to_i, $max_gallery, $probe_all_counts );
					if ( is_array( $cached_retry ) && ! empty( $cached_retry['items'] ) && isset( $cached_retry['stamp'] ) && $cached_retry['stamp'] === $retry_stamp ) {
						$result = $this->prepare_result(
							array(
								'items'         => $cached_retry['items'],
								'total'         => isset( $cached_retry['total'] ) ? (int) $cached_retry['total'] : count( $cached_retry['items'] ),
								'pages_fetched' => 0,
								'fetch_url'     => '',
							),
							$from_i,
							$to_i
						);
					}
				}
			}
		}

		$has_year_filter = ( $from !== '' || $to !== '' );
		$preview_meta    = array(
			'url'  => $url,
			'from' => $from,
			'to'   => $to,
		);
		$reset_url         = wp_nonce_url(
			add_query_arg( 'th_arkiv_reset', '1', admin_url( 'admin.php?page=timeline-history-arkiv' ) ),
			'th_arkiv_reset'
		);
		$has_preview       = is_array( $result ) && ! empty( $result['items'] );
		$layout_class      = 'th-arkiv-layout' . ( $has_preview ? '' : ' th-arkiv-layout--solo' );
		?>
		<div class="wrap th-arkiv-page">
			<div class="th-studio-header">
				<div>
					<h1><?php esc_html_e( 'Import fra Arkiv.dk', 'timeline-history' ); ?></h1>
				</div>
				<a href="<?php echo esc_url( $reset_url ); ?>" class="th-sf-btn th-sf-btn--ghost"><?php esc_html_e( 'Start forfra', 'timeline-history' ); ?></a>
			</div>
			<p class="th-studio__hint th-arkiv-lead">
				<?php esc_html_e( 'Søg efter lokalarkiv, tilføj evt. søgeord, og hent forhåndsvisning. Kun billeder (billeder=true). Ved import hentes visningsbilledet og renset tekst fra arkiv.dk.', 'timeline-history' ); ?>
			</p>

			<?php if ( $error ) : ?>
				<div class="th-arkiv-notice th-arkiv-notice--error"><p style="margin:0;"><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( is_array( $import_notice ) ) : ?>
				<div class="th-arkiv-notice th-arkiv-notice--success">
					<p style="margin:0 0 0.35rem;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: created, 2: updated, 3: skipped */
								__( 'Import færdig: %1$d oprettet, %2$d opdateret, %3$d sprunget over.', 'timeline-history' ),
								(int) $import_notice['created'],
								(int) $import_notice['updated'],
								(int) $import_notice['skipped']
							)
						);
						?>
					</p>
					<p style="margin:0;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=timeline-history-builder' ) ); ?>"><?php esc_html_e( 'Åbn Studio', 'timeline-history' ); ?></a>
					</p>
					<?php if ( ! empty( $import_notice['timeline'] ) ) : ?>
						<p class="th-studio__shortcode th-studio__shortcode--compact" style="margin-top:0.65rem;">
							<span class="th-studio__shortcode-label"><?php esc_html_e( 'Shortcode', 'timeline-history' ); ?></span>
							<code class="th-studio__embed-code th-studio__embed-code--inline">[timeline_history timeline="<?php echo esc_attr( $import_notice['timeline'] ); ?>"]</code>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( is_array( $import ) ) : ?>
				<div class="th-arkiv-notice th-arkiv-notice--success">
					<p style="margin:0 0 0.35rem;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: created, 2: updated (image/text), 3: skipped */
								__( 'Import færdig: %1$d oprettet, %2$d opdateret (større billede/tekst), %3$d sprunget over.', 'timeline-history' ),
								(int) $import['created'],
								isset( $import['updated'] ) ? (int) $import['updated'] : 0,
								(int) $import['skipped']
							)
						);
						?>
					</p>
					<p style="margin:0;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=timeline-history-builder' ) ); ?>"><?php esc_html_e( 'Åbn Studio', 'timeline-history' ); ?></a></p>
					<?php if ( ! empty( $import['timeline']['slug'] ) ) : ?>
						<p class="th-studio__shortcode th-studio__shortcode--compact" style="margin-top:0.65rem;">
							<span class="th-studio__shortcode-label"><?php esc_html_e( 'Shortcode', 'timeline-history' ); ?></span>
							<code class="th-studio__embed-code th-studio__embed-code--inline">[timeline_history timeline="<?php echo esc_attr( $import['timeline']['slug'] ); ?>"]</code>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $import['errors'] ) ) : ?>
						<ul style="margin:0.5rem 0 0;padding-left:1.1rem;">
							<?php foreach ( $import['errors'] as $err ) : ?>
								<li><?php echo esc_html( $err ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<style>
				.th-arkiv-archive-selected[hidden],
				#th_arkiv_archive_search_wrap[hidden] {
					display: none !important;
				}
			</style>
			<div id="th-arkiv-busy-bootstrap" class="th-arkiv-busy" hidden aria-hidden="true">
				<div class="th-arkiv-busy__panel" role="status">
					<span class="spinner is-active" aria-hidden="true"></span>
					<p class="th-arkiv-busy__title"><?php esc_html_e( 'Indlæser…', 'timeline-history' ); ?></p>
					<p class="th-arkiv-busy__detail"><?php esc_html_e( 'Vent mens siden opdateres.', 'timeline-history' ); ?></p>
				</div>
			</div>
			<div id="th-arkiv-busy" class="th-arkiv-busy" hidden aria-live="polite" aria-busy="false" aria-hidden="true">
				<div class="th-arkiv-busy__panel" role="status">
					<span class="spinner is-active" aria-hidden="true"></span>
					<p class="th-arkiv-busy__title" id="th-arkiv-busy-title"><?php esc_html_e( 'Arbejder…', 'timeline-history' ); ?></p>
					<p class="th-arkiv-busy__detail" id="th-arkiv-busy-detail"><?php esc_html_e( 'Det kan tage flere minutter ved mange poster. Lad fanen stå åben.', 'timeline-history' ); ?></p>
					<span class="th-arkiv-busy__count" id="th-arkiv-busy-count" hidden></span>
					<p class="th-arkiv-busy__detail" id="th-arkiv-busy-elapsed" style="margin-top:0.5rem;font-variant-numeric:tabular-nums;"></p>
				</div>
			</div>

					<form method="post" id="th-arkiv-import-form" class="th-story-form th-arkiv-form <?php echo esc_attr( $layout_class ); ?>" action="<?php echo esc_url( admin_url( 'admin.php?page=timeline-history-arkiv' ) ); ?>">
				<div class="th-arkiv-form-col">
						<?php wp_nonce_field( 'th_arkiv_import' ); ?>
						<input type="hidden" name="th_arkiv_action" id="th_arkiv_action_field" value="" />
						<input type="hidden" name="th_arkiv_archive" id="th_arkiv_archive" value="<?php echo $archive_id > 0 ? (int) $archive_id : ''; ?>" />
						<input type="hidden" name="th_arkiv_archive_label" id="th_arkiv_archive_label" value="<?php echo esc_attr( $archive_label ); ?>" />
						<input type="hidden" name="th_arkiv_url_manual" id="th_arkiv_url_manual" value="<?php echo $url_manual ? '1' : '0'; ?>" />

						<div class="th-studio__card">
							<div class="th-studio__card-head">
								<h2><?php esc_html_e( 'Søgning', 'timeline-history' ); ?></h2>
								<span class="th-studio__pill">arkiv.dk</span>
							</div>

							<div class="th-sf-field">
								<label class="th-sf-field__label" for="th_arkiv_archive_query"><?php esc_html_e( 'Lokalarkiv', 'timeline-history' ); ?></label>
								<p class="th-sf-field__hint"><?php esc_html_e( 'Samme arkivindeks som på arkiv.dk. Søgningen går live.', 'timeline-history' ); ?></p>
								<div id="th_arkiv_archive_selected" class="th-arkiv-archive-selected" <?php echo $archive_id > 0 && $archive_label !== '' ? '' : 'hidden'; ?>>
									<strong id="th_arkiv_archive_selected_name"><?php echo esc_html( $archive_label ); ?></strong>
									<button type="button" class="th-link-btn" id="th_arkiv_archive_clear" style="margin:0;"><?php esc_html_e( 'Skift arkiv', 'timeline-history' ); ?></button>
								</div>
								<div id="th_arkiv_archive_search_wrap" <?php echo $archive_id > 0 && $archive_label !== '' ? 'hidden' : ''; ?>>
									<input type="search" id="th_arkiv_archive_query" autocomplete="off"
										placeholder="<?php esc_attr_e( 'Skriv mindst 2 bogstaver (fx Ansager)…', 'timeline-history' ); ?>" />
									<ul id="th_arkiv_archive_results" class="th-arkiv-archive-results" hidden></ul>
								</div>
							</div>

							<div class="th-sf-field">
								<label class="th-sf-field__label" for="th_arkiv_search"><?php esc_html_e( 'Søgeord (valgfrit)', 'timeline-history' ); ?></label>
								<input type="search" name="th_arkiv_search" id="th_arkiv_search" value="<?php echo esc_attr( $search_string ); ?>" />
								<p class="th-sf-field__hint"><?php esc_html_e( 'Tom = alle billeder i det valgte lokalarkiv.', 'timeline-history' ); ?></p>
							</div>

							<div class="th-sf-field" id="th_arkiv_url_row">
								<label class="th-sf-field__label" for="th_arkiv_url"><?php esc_html_e( 'Søgelink', 'timeline-history' ); ?></label>
								<input type="url" name="th_arkiv_url" id="th_arkiv_url" readonly
									value="<?php echo esc_attr( $url ); ?>"
									placeholder="<?php esc_attr_e( 'Vises når du har valgt lokalarkiv', 'timeline-history' ); ?>" />
								<p class="th-sf-field__hint">
									<button type="button" class="th-link-btn" id="th_arkiv_url_toggle" style="margin:0;"><?php esc_html_e( 'Rediger link manuelt', 'timeline-history' ); ?></button>
								</p>
							</div>

							<div class="th-sf-field">
								<span class="th-sf-field__label"><?php esc_html_e( 'Årstal (valgfrit)', 'timeline-history' ); ?></span>
								<div class="th-arkiv-field-row">
									<label>
										<?php esc_html_e( 'Fra', 'timeline-history' ); ?>
										<input type="number" name="th_arkiv_from" min="1000" max="2100" value="<?php echo esc_attr( $from ); ?>" />
									</label>
									<label>
										<?php esc_html_e( 'Til', 'timeline-history' ); ?>
										<input type="number" name="th_arkiv_to" min="1000" max="2100" value="<?php echo esc_attr( $to ); ?>" />
									</label>
								</div>
							</div>

							<button type="submit" class="th-sf-btn th-sf-btn--secondary th-sf-btn--block" data-th-arkiv-action="preview"><?php esc_html_e( 'Hent forhåndsvisning', 'timeline-history' ); ?></button>
							<div id="th-arkiv-stale-notice" class="th-arkiv-stale-notice" hidden>
								<?php esc_html_e( 'Du har ændret søgningen. Hent forhåndsvisning igen.', 'timeline-history' ); ?>
							</div>
						</div>
				</div>

				<?php
				$ajax_nonce = wp_create_nonce( 'th_arkiv_admin' );
				?>
				<script>
					(function () {
						var thArkivAdmin = {
							ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
							nonce: <?php echo wp_json_encode( $ajax_nonce ); ?>,
							previewMeta: <?php echo wp_json_encode( is_array( $result ) && ! empty( $result['items'] ) ? $preview_meta : null ); ?>
						};

						var form = document.getElementById('th-arkiv-import-form');
						var busy = document.getElementById('th-arkiv-busy');
						var busyTitle = document.getElementById('th-arkiv-busy-title');
						var busyDetail = document.getElementById('th-arkiv-busy-detail');
						var busyCount = document.getElementById('th-arkiv-busy-count');
						var busyElapsed = document.getElementById('th-arkiv-busy-elapsed');
						var previewBlock = document.getElementById('th-arkiv-preview-block');
						var staleNotice = document.getElementById('th-arkiv-stale-notice');
						var busyTick = null;
						var busyStarted = 0;
						var defaultBusyDetail = busyDetail ? busyDetail.textContent : '';

						function clearNavBusy() {
							try { sessionStorage.removeItem('th_arkiv_nav_busy'); } catch (e) {}
							document.documentElement.classList.remove('th-arkiv-nav-busy');
							var boot = document.getElementById('th-arkiv-busy-bootstrap');
							if (boot) boot.hidden = true;
							hideBusy();
						}

						function hideBusy() {
							if (busyTick) {
								window.clearInterval(busyTick);
								busyTick = null;
							}
							if (busy) {
								busy.hidden = true;
								busy.setAttribute('aria-hidden', 'true');
								busy.setAttribute('aria-busy', 'false');
							}
							if (busyElapsed) busyElapsed.textContent = '';
							if (busyDetail && defaultBusyDetail) busyDetail.textContent = defaultBusyDetail;
						}

						function showBusy(title, detail, count) {
							if (!busy) return;
							if (busyTitle) busyTitle.textContent = title;
							if (busyDetail) busyDetail.textContent = detail || defaultBusyDetail;
							if (busyCount) {
								if (count > 0) {
									busyCount.hidden = false;
									busyCount.textContent = count + ' <?php echo esc_js( __( 'poster', 'timeline-history' ) ); ?>';
								} else {
									busyCount.hidden = true;
									busyCount.textContent = '';
								}
							}
							busy.hidden = false;
							busy.setAttribute('aria-hidden', 'false');
							busy.setAttribute('aria-busy', 'true');
							busyStarted = Date.now();
							if (busyTick) window.clearInterval(busyTick);
							busyTick = window.setInterval(function () {
								if (!busyElapsed) return;
								var s = Math.floor((Date.now() - busyStarted) / 1000);
								var m = Math.floor(s / 60);
								var r = s % 60;
								busyElapsed.textContent = (m > 0 ? (m + ' min ') : '') + r + ' s';
							}, 1000);
						}

						function markNavBusy() {
							try { sessionStorage.setItem('th_arkiv_nav_busy', '1'); } catch (e) {}
							document.documentElement.classList.add('th-arkiv-nav-busy');
						}

						function runAjaxJob(action, title, detail, count) {
							if (!form) return;
							syncUrlBeforeSubmit();
							showBusy(title, detail, count);
							var fd = new FormData(form);
							fd.set('action', action === 'preview' ? 'th_arkiv_preview' : 'th_arkiv_import');
							fd.set('nonce', thArkivAdmin.nonce);
							fetch(thArkivAdmin.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
								.then(function (r) { return r.json(); })
								.then(function (json) {
									if (json && json.success && json.data && json.data.redirect) {
										markNavBusy();
										window.location.assign(json.data.redirect);
										return;
									}
									hideBusy();
									var msg = (json && json.data && json.data.message) ? json.data.message
										: '<?php echo esc_js( __( 'Noget gik galt. Prøv igen.', 'timeline-history' ) ); ?>';
									window.alert(msg);
								})
								.catch(function () {
									hideBusy();
									window.alert('<?php echo esc_js( __( 'Forbindelsen til serveren mislykkedes. Prøv igen.', 'timeline-history' ) ); ?>');
								});
						}

						function bindActionButtons() {
							var actionField = document.getElementById('th_arkiv_action_field');
							if (!form || !actionField) return;
							form.querySelectorAll('[data-th-arkiv-action]').forEach(function (btn) {
								btn.addEventListener('click', function () {
									actionField.value = btn.getAttribute('data-th-arkiv-action') || '';
								});
							});
						}
						bindActionButtons();

						function invalidatePreview() {
							if (previewBlock) {
								previewBlock.hidden = true;
							}
							if (staleNotice) {
								staleNotice.hidden = false;
							}
						}

						function currentSearchMeta() {
							var urlEl = document.getElementById('th_arkiv_url');
							var fromEl = form && form.querySelector('[name="th_arkiv_from"]');
							var toEl = form && form.querySelector('[name="th_arkiv_to"]');
							return {
								url: urlEl ? urlEl.value : '',
								from: fromEl && fromEl.value ? fromEl.value : '',
								to: toEl && toEl.value ? toEl.value : ''
							};
						}

						function maybeInvalidatePreview() {
							if (!previewBlock || !thArkivAdmin.previewMeta) return;
							var cur = currentSearchMeta();
							var ref = thArkivAdmin.previewMeta;
							if (cur.url !== ref.url || cur.from !== ref.from || cur.to !== ref.to) {
								invalidatePreview();
							}
						}

						if (form) {
							form.addEventListener('submit', function (ev) {
								var actionField = document.getElementById('th_arkiv_action_field');
								var submitter = ev.submitter;
								var action = actionField && actionField.value ? actionField.value : '';
								if (!action && submitter) {
									action = submitter.getAttribute('data-th-arkiv-action') || submitter.value || '';
									if (actionField) actionField.value = action;
								}
								if (!action) {
									ev.preventDefault();
									window.alert('<?php echo esc_js( __( 'Vælg en handling (forhåndsvisning eller import).', 'timeline-history' ) ); ?>');
									return;
								}
								if (action === 'import' || action === 'preview') {
									ev.preventDefault();
									var archiveId = document.getElementById('th_arkiv_archive');
									var urlEl = document.getElementById('th_arkiv_url');
									var manual = document.getElementById('th_arkiv_url_manual');
									var hasArchive = archiveId && archiveId.value;
									var hasUrl = urlEl && urlEl.value;
									var manualMode = manual && manual.value === '1';
									if (!hasUrl || (!hasArchive && !manualMode)) {
										window.alert('<?php echo esc_js( __( 'Vælg et lokalarkiv, eller rediger søgelinket manuelt.', 'timeline-history' ) ); ?>');
										return;
									}
									if (action === 'import') {
										var selected = form.querySelectorAll('.th-arkiv-pick:checked:not(:disabled)').length;
										if (!selected) {
											window.alert('<?php echo esc_js( __( 'Vælg mindst én post at importere.', 'timeline-history' ) ); ?>');
											return;
										}
										var timelineEl = document.getElementById('th_arkiv_timeline');
										if (!timelineEl || !timelineEl.value.trim()) {
											window.alert('<?php echo esc_js( __( 'Angiv et navn til tidslinjen før import.', 'timeline-history' ) ); ?>');
											return;
										}
										runAjaxJob(
											'import',
											'<?php echo esc_js( __( 'Importerer valgte poster…', 'timeline-history' ) ); ?>',
											'<?php echo esc_js( __( 'Henter visningsbilleder og tekst fra arkiv.dk. Lad fanen stå åben.', 'timeline-history' ) ); ?>',
											selected
										);
									} else {
										runAjaxJob(
											'preview',
											'<?php echo esc_js( __( 'Henter forhåndsvisning…', 'timeline-history' ) ); ?>',
											'<?php echo esc_js( __( 'Henter søgeresultater fra arkiv.dk. Det kan tage lang tid ved store arkiver.', 'timeline-history' ) ); ?>',
											0
										);
									}
								}
							});
						}

						var base = 'https://arkiv.dk/soeg?';
						var archiveIdEl = document.getElementById('th_arkiv_archive');
						var archiveLabelEl = document.getElementById('th_arkiv_archive_label');
						var searchEl = document.getElementById('th_arkiv_search');
						var urlEl = document.getElementById('th_arkiv_url');
						var urlManualEl = document.getElementById('th_arkiv_url_manual');
						var urlToggle = document.getElementById('th_arkiv_url_toggle');
						var archiveQuery = document.getElementById('th_arkiv_archive_query');
						var archiveResults = document.getElementById('th_arkiv_archive_results');
						var archiveSelected = document.getElementById('th_arkiv_archive_selected');
						var archiveSelectedName = document.getElementById('th_arkiv_archive_selected_name');
						var archiveSearchWrap = document.getElementById('th_arkiv_archive_search_wrap');
						var archiveClear = document.getElementById('th_arkiv_archive_clear');
						var searchTimer = null;

						function syncUrlBeforeSubmit() {
							if (!urlEl || isUrlManual()) return;
							urlEl.value = buildUrl();
						}

						function isUrlManual() {
							return urlManualEl && urlManualEl.value === '1';
						}

						function setUrlManual(on) {
							if (urlManualEl) urlManualEl.value = on ? '1' : '0';
							if (urlEl) {
								urlEl.readOnly = !on;
							}
							if (urlToggle) {
								urlToggle.textContent = on
									? '<?php echo esc_js( __( 'Brug lokalarkiv i stedet', 'timeline-history' ) ); ?>'
									: '<?php echo esc_js( __( 'Rediger link manuelt', 'timeline-history' ) ); ?>';
							}
						}

						if (urlManualEl && urlManualEl.value === '1') {
							setUrlManual(true);
						}

						function buildUrl() {
							var params = ['billeder=true'];
							if (archiveIdEl && archiveIdEl.value) {
								params.push('valgtearkiverids=' + encodeURIComponent(archiveIdEl.value));
							}
							if (searchEl && searchEl.value.trim() !== '') {
								params.push('searchstring=' + encodeURIComponent(searchEl.value.trim()));
							}
							if (!archiveIdEl || !archiveIdEl.value) {
								return '';
							}
							return base + params.join('&');
						}

						function syncUrl() {
							if (!urlEl || isUrlManual()) return;
							urlEl.value = buildUrl();
							maybeInvalidatePreview();
						}

						function selectArchive(id, name) {
							if (archiveIdEl) archiveIdEl.value = String(id);
							if (archiveLabelEl) archiveLabelEl.value = name;
							if (archiveSelectedName) archiveSelectedName.textContent = name;
							if (archiveSelected) archiveSelected.hidden = false;
							if (archiveSearchWrap) archiveSearchWrap.hidden = true;
							if (archiveResults) {
								archiveResults.hidden = true;
								archiveResults.innerHTML = '';
							}
							if (archiveQuery) archiveQuery.value = '';
							setUrlManual(false);
							syncUrl();
						}

						if (archiveClear) {
							archiveClear.addEventListener('click', function () {
								if (archiveIdEl) archiveIdEl.value = '';
								if (archiveLabelEl) archiveLabelEl.value = '';
								if (archiveSelected) archiveSelected.hidden = true;
								if (archiveSearchWrap) archiveSearchWrap.hidden = false;
								if (urlEl && !isUrlManual()) urlEl.value = '';
								invalidatePreview();
								maybeInvalidatePreview();
							});
						}

						if (urlToggle) {
							urlToggle.addEventListener('click', function () {
								setUrlManual(!isUrlManual());
								if (!isUrlManual()) syncUrl();
								maybeInvalidatePreview();
							});
						}

						if (searchEl) {
							searchEl.addEventListener('input', function () {
								if (!isUrlManual()) syncUrl();
								else maybeInvalidatePreview();
							});
						}

						if (urlEl) {
							urlEl.addEventListener('input', function () {
								maybeInvalidatePreview();
							});
						}

						form && form.querySelectorAll('[name="th_arkiv_from"], [name="th_arkiv_to"]').forEach(function (el) {
							el.addEventListener('input', maybeInvalidatePreview);
						});

						function renderArchiveResults(results) {
							if (!archiveResults) return;
							archiveResults.innerHTML = '';
							if (!results.length) {
								var li = document.createElement('li');
								var btn = document.createElement('button');
								btn.type = 'button';
								btn.disabled = true;
								btn.textContent = '<?php echo esc_js( __( 'Ingen arkiver fundet', 'timeline-history' ) ); ?>';
								li.appendChild(btn);
								archiveResults.appendChild(li);
							} else {
								results.forEach(function (row) {
									var li = document.createElement('li');
									var btn = document.createElement('button');
									btn.type = 'button';
									btn.textContent = row.name;
									btn.addEventListener('click', function () {
										selectArchive(row.id, row.name);
									});
									li.appendChild(btn);
									archiveResults.appendChild(li);
								});
							}
							archiveResults.hidden = false;
						}

						if (archiveQuery) {
							archiveQuery.addEventListener('input', function () {
								var term = archiveQuery.value.trim();
								clearTimeout(searchTimer);
								if (term.length < 2) {
									if (archiveResults) {
										archiveResults.hidden = true;
										archiveResults.innerHTML = '';
									}
									return;
								}
								searchTimer = setTimeout(function () {
									var reqUrl = thArkivAdmin.ajaxUrl + '?action=th_arkiv_search_archives&nonce=' + encodeURIComponent(thArkivAdmin.nonce) + '&term=' + encodeURIComponent(term);
									fetch(reqUrl, { credentials: 'same-origin' })
										.then(function (r) { return r.json(); })
										.then(function (json) {
											if (json && json.success && json.data && json.data.results) {
												renderArchiveResults(json.data.results);
											}
										})
										.catch(function () {});
								}, 280);
							});
						}

						window.addEventListener('load', function () {
							window.setTimeout(clearNavBusy, 150);
						});
					})();
				</script>

				<div class="th-arkiv-preview-col">
				<?php if ( is_array( $result ) && ! empty( $result['items'] ) ) : ?>
					<?php
					$shown   = count( $result['items'] );
					$arkiv   = (int) $result['total'];
					$matched = isset( $result['matched'] ) ? (int) $result['matched'] : 0;
					$fuzzy   = isset( $result['fuzzy'] ) ? (int) $result['fuzzy'] : 0;
					?>
					<div class="th-studio__card" id="th-arkiv-preview-block">
					<div class="th-studio__card-head th-arkiv-preview-head">
						<div class="th-arkiv-preview-head__main">
							<div class="th-arkiv-preview-head__title">
								<h2><?php esc_html_e( 'Forhåndsvisning', 'timeline-history' ); ?></h2>
								<span class="th-studio__pill"><?php echo (int) $shown; ?></span>
							</div>
							<p class="th-arkiv-summary">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: shown count, 2: arkiv.dk total */
										__( '%1$d poster hentet (arkiv.dk melder %2$d)', 'timeline-history' ),
										$shown,
										$arkiv
									)
								);
								?>
							</p>
							<?php if ( $has_year_filter ) : ?>
								<p class="th-arkiv-legend">
									<span><span class="th-arkiv-pill th-arkiv-pill--match"><?php esc_html_e( 'Præcis', 'timeline-history' ); ?></span> <span class="th-arkiv-count"><?php echo (int) $matched; ?></span> <?php esc_html_e( 'forudvalgt', 'timeline-history' ); ?></span>
									<span><span class="th-arkiv-pill th-arkiv-pill--fuzzy"><?php esc_html_e( 'Upræcis', 'timeline-history' ); ?></span> <span class="th-arkiv-count"><?php echo (int) $fuzzy; ?></span> <?php esc_html_e( 'manuelt valg', 'timeline-history' ); ?></span>
									<span><?php esc_html_e( 'Øvrige ligger uden for intervallet og er ikke valgt.', 'timeline-history' ); ?></span>
								</p>
							<?php endif; ?>
						</div>
						<div class="th-arkiv-import-actions">
							<label class="th-arkiv-timeline-field" for="th_arkiv_timeline">
								<span class="th-arkiv-timeline-field__label"><?php esc_html_e( 'Tidslinjenavn', 'timeline-history' ); ?></span>
								<input type="text" name="th_arkiv_timeline" id="th_arkiv_timeline" class="th-arkiv-timeline-field__input" list="th-arkiv-timelines"
									value="<?php echo esc_attr( $timeline_name ); ?>"
									placeholder="<?php esc_attr_e( 'Fx. Ansager 1940-1942', 'timeline-history' ); ?>"
									autocomplete="off" />
							</label>
							<?php if ( ! empty( $existing_sets ) ) : ?>
								<datalist id="th-arkiv-timelines">
									<?php foreach ( $existing_sets as $set ) : ?>
										<option value="<?php echo esc_attr( $set['name'] ); ?>"></option>
									<?php endforeach; ?>
								</datalist>
							<?php endif; ?>
							<button type="submit" class="th-sf-btn" data-th-arkiv-action="import">
								<?php esc_html_e( 'Importér valgte', 'timeline-history' ); ?>
							</button>
						</div>
					</div>
					<p class="th-sf-field__hint"><?php esc_html_e( 'Vælg poster, angiv tidslinjenavn og importér. Billederne forbliver eksterne links til arkiv.dk.', 'timeline-history' ); ?></p>
					<div class="th-arkiv-select" role="group" aria-label="<?php esc_attr_e( 'Markering', 'timeline-history' ); ?>">
						<?php if ( $has_year_filter ) : ?>
							<button type="button" class="th-sf-btn th-sf-btn--secondary" id="th-arkiv-sel-precise"><?php esc_html_e( 'Kun præcise', 'timeline-history' ); ?></button>
							<button type="button" class="th-sf-btn th-sf-btn--secondary" id="th-arkiv-sel-fuzzy"><?php esc_html_e( '+ upræcise', 'timeline-history' ); ?></button>
						<?php endif; ?>
						<button type="button" class="th-sf-btn th-sf-btn--secondary" id="th-arkiv-sel-all"><?php esc_html_e( 'Alle', 'timeline-history' ); ?></button>
						<button type="button" class="th-sf-btn th-sf-btn--secondary" id="th-arkiv-sel-none"><?php esc_html_e( 'Ingen', 'timeline-history' ); ?></button>
						<span class="th-sf-field__hint" id="th-arkiv-sel-count"></span>
					</div>
					<div class="th-arkiv-table-wrap"><table class="th-arkiv-table" id="th-arkiv-table">
						<thead>
							<tr>
								<th style="width:36px;"></th>
								<th style="width:72px;"><?php esc_html_e( 'Billede', 'timeline-history' ); ?></th>
								<th><?php esc_html_e( 'Titel', 'timeline-history' ); ?></th>
								<th style="width:140px;"><?php esc_html_e( 'År', 'timeline-history' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $result['items'] as $item ) : ?>
								<?php
								$aid     = (string) $item['arkiv_id'];
								$fit     = isset( $item['year_fit'] ) ? (string) $item['year_fit'] : 'match';
								$checked          = ( ! $has_year_filter || 'match' === $fit );
								$year_txt = $item['year_label'] !== '' ? $item['year_label'] : (string) $item['year'];
								$pill     = '';
								$pill_cls = '';
								if ( $has_year_filter ) {
									if ( 'match' === $fit ) {
										$pill     = __( 'Præcis', 'timeline-history' );
										$pill_cls = 'th-arkiv-pill--match';
									} elseif ( 'fuzzy' === $fit ) {
										$pill     = __( 'Upræcis', 'timeline-history' );
										$pill_cls = 'th-arkiv-pill--fuzzy';
									} elseif ( 'unknown' === $fit ) {
										$pill     = __( 'Uden år', 'timeline-history' );
										$pill_cls = 'th-arkiv-pill--unknown';
									} else {
										$pill     = __( 'Udenfor', 'timeline-history' );
										$pill_cls = 'th-arkiv-pill--outside';
									}
								}
								$is_series     = Timeline_History_Arkiv_Client::item_likely_gallery_series( $item )
									|| ( isset( $item['image_count'] ) && (int) $item['image_count'] > 1 );
								$row_picked    = $checked ? ' th-arkiv-row-wrap--picked' : '';
								?>
								<tr class="th-arkiv-row-wrap th-arkiv-row--<?php echo esc_attr( $fit ); ?><?php echo esc_attr( $row_picked ); ?>">
									<td class="th-arkiv-pick-cell">
										<input
											type="checkbox"
											class="th-arkiv-pick"
											name="th_arkiv_ids[]"
											value="<?php echo esc_attr( $aid ); ?>"
											data-fit="<?php echo esc_attr( $fit ); ?>"
											<?php checked( $checked ); ?>
										/>
									</td>
									<td>
										<?php if ( ! empty( $item['image'] ) ) : ?>
											<img src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy" />
										<?php endif; ?>
									</td>
									<td class="th-arkiv-title-cell">
										<div class="th-arkiv-title-line">
											<strong><?php echo esc_html( $item['title'] ); ?></strong>
											<?php if ( $is_series ) : ?>
												<span class="th-arkiv-pill th-arkiv-pill--series"><?php esc_html_e( 'Serie', 'timeline-history' ); ?></span>
											<?php endif; ?>
										</div>
										<a href="<?php echo esc_url( $item['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Åbn på arkiv.dk', 'timeline-history' ); ?></a>
									</td>
									<td class="th-arkiv-year">
										<?php echo esc_html( $year_txt ); ?>
										<?php if ( $pill ) : ?>
											<br /><span class="th-arkiv-pill <?php echo esc_attr( $pill_cls ); ?>"><?php echo esc_html( $pill ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
					<script>
						(function () {
							var rows = Array.prototype.slice.call(document.querySelectorAll('.th-arkiv-pick'));
							var countEl = document.getElementById('th-arkiv-sel-count');
							function enabled(rowsList) {
								return rowsList.filter(function (el) { return !el.disabled; });
							}
							function syncRowPick(el) {
								var tr = el.closest('tr');
								if (tr) {
									tr.classList.toggle('th-arkiv-row-wrap--picked', !!el.checked);
								}
							}
							function setChecked(predicate) {
								enabled(rows).forEach(function (el) {
									el.checked = !!predicate(el);
									syncRowPick(el);
								});
								updateCount();
							}
							function updateCount() {
								if (!countEl) return;
								var n = enabled(rows).filter(function (el) { return el.checked; }).length;
								countEl.textContent = n ? (n + ' valgt') : '';
							}
							var precise = document.getElementById('th-arkiv-sel-precise');
							var fuzzy = document.getElementById('th-arkiv-sel-fuzzy');
							var all = document.getElementById('th-arkiv-sel-all');
							var none = document.getElementById('th-arkiv-sel-none');
							if (precise) {
								precise.addEventListener('click', function () {
									setChecked(function (el) { return el.getAttribute('data-fit') === 'match'; });
								});
							}
							if (fuzzy) {
								fuzzy.addEventListener('click', function () {
									setChecked(function (el) {
										var fit = el.getAttribute('data-fit');
										return fit === 'match' || fit === 'fuzzy' || fit === 'unknown';
									});
								});
							}
							if (all) {
								all.addEventListener('click', function () {
									setChecked(function () { return true; });
								});
							}
							if (none) {
								none.addEventListener('click', function () {
									setChecked(function () { return false; });
								});
							}
							rows.forEach(function (el) {
								syncRowPick(el);
								el.addEventListener('change', function () {
									syncRowPick(el);
									updateCount();
								});
							});
							updateCount();
						})();
					</script>
					</div>
				<?php elseif ( is_array( $result ) ) : ?>
					<div class="th-arkiv-notice th-arkiv-notice--error"><p style="margin:0;"><?php esc_html_e( 'Ingen resultater med de valgte filtre.', 'timeline-history' ); ?></p></div>
				<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}
}
