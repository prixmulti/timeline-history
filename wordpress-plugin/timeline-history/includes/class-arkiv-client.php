<?php
/**
 * Fetch and parse arkiv.dk search result pages.
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Arkiv_Client {

	public const SOURCE_URL_KEY     = '_th_source_url';
	public const ARKIV_ID_KEY       = '_th_arkiv_id';
	public const BEMAERKNING_KEY    = '_th_arkiv_bemaerkning';
	public const IMAGE_COUNT_KEY    = '_th_arkiv_image_count_total';
	public const IMAGE_CAPTIONS_KEY = '_th_story_image_captions';

	/** Default cap on gallery images per story (0 = no cap). */
	public const DEFAULT_MAX_GALLERY_IMAGES = 50;

	/**
	 * Build a fetchable arkiv.dk search URL with year bounds.
	 *
	 * @param string   $url  User-pasted search URL.
	 * @param int|null $from Year from (inclusive).
	 * @param int|null $to   Year to (inclusive).
	 * @param int      $page Page number (1-based).
	 */
	public static function build_search_url( string $url, ?int $from, ?int $to, int $page = 1 ): string|WP_Error {
		$url = trim( $url );
		if ( $url === '' ) {
			return new WP_Error( 'th_arkiv_url', __( 'Indsæt et arkiv.dk-søgelink.', 'timeline-history' ) );
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || false === stripos( (string) $parts['host'], 'arkiv.dk' ) ) {
			return new WP_Error( 'th_arkiv_host', __( 'Linket skal pege på arkiv.dk.', 'timeline-history' ) );
		}

		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		$path = isset( $parts['path'] ) && $parts['path'] !== '' ? $parts['path'] : '/soeg';
		if ( false === strpos( $path, 'soeg' ) ) {
			$path = '/soeg';
		}

		if ( $from ) {
			$query['fra'] = (string) $from;
		}
		if ( $to ) {
			$query['til'] = (string) $to;
		}
		$query['page'] = max( 1, $page );

		$scheme = ! empty( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$host   = $parts['host'];
		return $scheme . '://' . $host . $path . '?' . http_build_query( $query );
	}

	/** Transient key for cached archive catalog from arkiv.dk. */
	public const ARCHIVES_TRANSIENT = 'th_arkiv_archives_catalog_v1';

	/**
	 * Build a standard arkiv.dk search URL from parts.
	 *
	 * @param int|null $archive_id valgtearkiverids.
	 */
	public static function compose_search_url( ?int $archive_id, string $search_string = '', bool $images_only = true ): string {
		$search_string = trim( $search_string );
		if ( ( ! $archive_id || $archive_id <= 0 ) && $search_string === '' ) {
			return '';
		}
		$query = array();
		if ( $images_only ) {
			$query['billeder'] = 'true';
		}
		if ( $archive_id && $archive_id > 0 ) {
			$query['valgtearkiverids'] = (string) $archive_id;
		}
		if ( $search_string !== '' ) {
			$query['searchstring'] = $search_string;
		}
		return 'https://arkiv.dk/soeg?' . http_build_query( $query );
	}

	/**
	 * Typeahead search for local archives (same API as arkiv.dk search filter).
	 *
	 * @return array{total: int, results: array<int, array{id: int, name: string}>}|\WP_Error
	 */
	public static function search_archives( string $search_term, int $page_num = 1, int $page_size = 30 ) {
		$chunk = self::fetch_archives_api_page( $page_num, max( 5, min( 50, $page_size ) ), trim( $search_term ) );
		if ( is_wp_error( $chunk ) ) {
			return $chunk;
		}
		$results = array();
		foreach ( $chunk['results'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$name = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';
			if ( $name === '' ) {
				continue;
			}
			$results[] = array(
				'id'   => $id,
				'name' => $name,
			);
		}
		return array(
			'total'   => (int) $chunk['total'],
			'results' => $results,
		);
	}

	/**
	 * Parse archive id and search string from an arkiv.dk search URL.
	 *
	 * @return array{archive_id: int, searchstring: string, images_only: bool}
	 */
	public static function parse_search_url_parts( string $url ): array {
		$archive_id    = 0;
		$searchstring  = '';
		$images_only   = false;
		$parts         = wp_parse_url( trim( $url ) );
		$query         = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		if ( ! empty( $query['valgtearkiverids'] ) ) {
			$raw = is_array( $query['valgtearkiverids'] ) ? reset( $query['valgtearkiverids'] ) : $query['valgtearkiverids'];
			$raw = is_string( $raw ) ? explode( ',', $raw )[0] : (string) $raw;
			$archive_id = (int) preg_replace( '/\D/', '', $raw );
		}
		if ( ! empty( $query['searchstring'] ) && is_string( $query['searchstring'] ) ) {
			$searchstring = $query['searchstring'];
		}
		if ( isset( $query['billeder'] ) && ( $query['billeder'] === 'true' || $query['billeder'] === true || $query['billeder'] === '1' ) ) {
			$images_only = true;
		}
		return array(
			'archive_id'   => $archive_id,
			'searchstring' => $searchstring,
			'images_only'  => $images_only,
		);
	}

	/**
	 * List contributing archives (id + name) for dropdowns. Cached 24h.
	 *
	 * @return array<int, array{id: int, name: string}>|\WP_Error
	 */
	public static function list_archives( bool $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::ARCHIVES_TRANSIENT );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$fetched = self::fetch_archives_catalog();
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}

		set_transient( self::ARCHIVES_TRANSIENT, $fetched, DAY_IN_SECONDS );
		return $fetched;
	}

	/**
	 * Pull full archive list from arkiv.dk JSON (same source as their search filter).
	 *
	 * @return array<int, array{id: int, name: string}>|\WP_Error
	 */
	public static function fetch_archives_catalog() {
		$page_size = 500;
		$page      = 1;
		$all       = array();
		$total     = 0;

		do {
			$chunk = self::fetch_archives_api_page( $page, $page_size, '' );
			if ( is_wp_error( $chunk ) ) {
				return $chunk;
			}
			$total = (int) $chunk['total'];
			foreach ( $chunk['results'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				if ( $id <= 0 ) {
					continue;
				}
				$name = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';
				if ( $name === '' ) {
					continue;
				}
				$all[ $id ] = array(
					'id'   => $id,
					'name' => $name,
				);
			}
			$page++;
		} while ( count( $all ) < $total && ! empty( $chunk['results'] ) && $page < 50 );

		$list = array_values( $all );
		usort(
			$list,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $list;
	}

	/**
	 * One page from GET https://arkiv.dk/Soeg/Arkiver
	 *
	 * @return array{total: int, results: array<int, array{id: int, text: string}>}|\WP_Error
	 */
	private static function fetch_archives_api_page( int $page_num, int $page_size, string $search_term ) {
		$endpoint = add_query_arg(
			array(
				'pageSize'   => max( 1, min( 500, $page_size ) ),
				'pageNum'    => max( 1, $page_num ),
				'searchTerm' => $search_term,
			),
			'https://arkiv.dk/Soeg/Arkiver'
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'     => 25,
				'redirection' => 3,
				'headers'     => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; TimelineHistoryWordPress/' . TIMELINE_HISTORY_VERSION . '; +https://wordpress.org)',
					'Accept'     => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 || $body === '' ) {
			return new WP_Error(
				'th_arkiv_archives_http',
				sprintf(
					/* translators: %d: HTTP status */
					__( 'Kunne ikke hente arkivlisten fra arkiv.dk (HTTP %d).', 'timeline-history' ),
					$code
				)
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['Results'] ) || ! is_array( $data['Results'] ) ) {
			return new WP_Error( 'th_arkiv_archives_json', __( 'Ugyldigt svar fra arkiv.dk (arkivliste).', 'timeline-history' ) );
		}

		return array(
			'total'   => isset( $data['Total'] ) ? (int) $data['Total'] : count( $data['Results'] ),
			'results' => $data['Results'],
		);
	}

	/**
	 * Fetch one search page and parse rows.
	 *
	 * @return array{items: array<int, array>, total: int, page: int, fetch_url: string}|\WP_Error
	 */
	public static function fetch_page( string $url, ?int $from = null, ?int $to = null, int $page = 1 ) {
		$fetch_url = self::build_search_url( $url, $from, $to, $page );
		if ( is_wp_error( $fetch_url ) ) {
			return $fetch_url;
		}

		$response = wp_remote_get(
			$fetch_url,
			array(
				'timeout'    => 25,
				'redirection' => 3,
				'headers'    => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; TimelineHistoryWordPress/' . TIMELINE_HISTORY_VERSION . '; +https://wordpress.org)',
					'Accept'     => 'text/html,application/xhtml+xml',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 || $body === '' ) {
			return new WP_Error(
				'th_arkiv_http',
				sprintf(
					/* translators: %d: HTTP status */
					__( 'Kunne ikke hente arkiv.dk (HTTP %d).', 'timeline-history' ),
					$code
				)
			);
		}

		$parsed = self::parse_html( $body );
		$parsed['page']      = $page;
		$parsed['fetch_url'] = $fetch_url;
		return $parsed;
	}

	/**
	 * Fetch multiple pages (capped). Keeps arkiv.dk's full (loose) result set.
	 * Year bounds are used for classification / pre-selection, not for dropping rows.
	 *
	 * @return array{items: array<int, array>, total: int, matched: int, fuzzy: int, pages_fetched: int, fetch_url: string}|\WP_Error
	 */
	public static function fetch_search( string $url, ?int $from = null, ?int $to = null, int $max_pages = 40 ) {
		$first = self::fetch_page( $url, $from, $to, 1 );
		if ( is_wp_error( $first ) ) {
			return $first;
		}

		$items = $first['items'];
		$total = (int) $first['total'];
		$pages = 1;

		// Rough page size from first response; arkiv typically returns ~18-20.
		$page_size = max( 1, count( $first['items'] ) );
		$max_pages = max( 1, min( 50, $max_pages ) );
		$needed    = $total > 0 ? (int) ceil( $total / $page_size ) : 1;
		$to_fetch  = min( $max_pages, max( 1, $needed ) );

		for ( $p = 2; $p <= $to_fetch; $p++ ) {
			$next = self::fetch_page( $url, $from, $to, $p );
			if ( is_wp_error( $next ) || empty( $next['items'] ) ) {
				break;
			}
			$items = array_merge( $items, $next['items'] );
			$pages++;
		}

		// Deduplicate by arkiv id.
		$seen = array();
		$out  = array();
		foreach ( $items as $item ) {
			$id = (string) $item['arkiv_id'];
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$out[]       = $item;
		}

		$matched = 0;
		$fuzzy   = 0;
		foreach ( $out as &$item ) {
			$fit              = self::year_fit( $item, $from, $to );
			$item['year_fit'] = $fit;
			if ( 'match' === $fit ) {
				$matched++;
			} elseif ( 'fuzzy' === $fit || 'unknown' === $fit ) {
				$fuzzy++;
			}
		}
		unset( $item );

		$out = self::sort_items_by_year_fit( $out );

		return array(
			'items'         => $out,
			'total'         => $total,
			'matched'       => $matched,
			'fuzzy'         => $fuzzy,
			'pages_fetched' => $pages,
			'fetch_url'     => $first['fetch_url'],
		);
	}

	/**
	 * Sort: precise matches first, then fuzzy/unknown, then outside.
	 *
	 * @param array<int, array> $items Items.
	 * @return array<int, array>
	 */
	public static function sort_items_by_year_fit( array $items ): array {
		$rank = array(
			'match'   => 0,
			'fuzzy'   => 1,
			'unknown' => 2,
			'outside' => 3,
		);
		usort(
			$items,
			static function ( $a, $b ) use ( $rank ) {
				$fa = isset( $a['year_fit'] ) ? (string) $a['year_fit'] : 'unknown';
				$fb = isset( $b['year_fit'] ) ? (string) $b['year_fit'] : 'unknown';
				$ra = isset( $rank[ $fa ] ) ? $rank[ $fa ] : 9;
				$rb = isset( $rank[ $fb ] ) ? $rank[ $fb ] : 9;
				if ( $ra !== $rb ) {
					return $ra - $rb;
				}
				$ya = isset( $a['year'] ) ? (int) $a['year'] : 0;
				$yb = isset( $b['year'] ) ? (int) $b['year'] : 0;
				if ( $ya !== $yb ) {
					return $ya - $yb;
				}
				return strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
			}
		);
		return $items;
	}

	/**
	 * Extract year numbers from an arkiv item.
	 *
	 * @return int[]
	 */
	public static function item_years( array $item ): array {
		$years = array();
		if ( ! empty( $item['year'] ) ) {
			$years[] = (int) $item['year'];
		}
		$label = isset( $item['year_label'] ) ? (string) $item['year_label'] : '';
		if ( $label !== '' && preg_match_all( '/\b((?:1[0-9]|20)\d{2})\b/', $label, $m ) ) {
			foreach ( $m[1] as $y ) {
				$years[] = (int) $y;
			}
		}
		return array_values( array_unique( array_filter( $years ) ) );
	}

	/**
	 * Classify year against requested range.
	 *
	 * - match: clear year(s) fully inside [from, to]
	 * - fuzzy: approximate / decade / span that only overlaps
	 * - outside: clear years entirely outside range
	 * - unknown: no usable year
	 *
	 * @param array    $item Parsed arkiv item.
	 * @param int|null $from Year from.
	 * @param int|null $to   Year to.
	 */
	public static function year_fit( array $item, ?int $from, ?int $to ): string {
		if ( ! $from && ! $to ) {
			return 'match';
		}

		$label = isset( $item['year_label'] ) ? (string) $item['year_label'] : '';
		$years = self::item_years( $item );
		if ( empty( $years ) ) {
			return 'unknown';
		}

		$min = min( $years );
		$max = max( $years );

		if ( $from && $max < $from ) {
			return 'outside';
		}
		if ( $to && $min > $to ) {
			return 'outside';
		}

		$approx = (bool) preg_match(
			'/\b(ca\.?|circa|cirka|omkring|approx|o\.|før|efter|senere|tidligere|midten|slutningen|begyndelsen)\b/iu',
			$label
		);
		$decade = (bool) preg_match( "/\\d{4}\\s*'?\\s*erne\\b/iu", $label );
		$unsure = false !== strpos( $label, '?' );

		// Span wider than the requested window → treat as imprecise opt-in.
		$extends = ( $from && $min < $from ) || ( $to && $max > $to );

		if ( $approx || $decade || $unsure || $extends ) {
			return 'fuzzy';
		}

		return 'match';
	}

	/**
	 * @param array $item Parsed arkiv item.
	 */
	public static function item_matches_year_range( array $item, ?int $from, ?int $to ): bool {
		return 'match' === self::year_fit( $item, $from, $to );
	}

	/**
	 * Decode arkiv.dk / HTML entity soup into readable UTF-8 text.
	 * Handles double-encoding, numeric entities (&#8211;, &#xD;) and stray CR/LF.
	 */
	public static function decode_text( string $text ): string {
		$out  = wp_specialchars_decode( $text, ENT_QUOTES );
		$prev = null;
		for ( $i = 0; $i < 4 && $out !== $prev; $i++ ) {
			$prev = $out;
			$out  = html_entity_decode( $out, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		// Literal leftover entity strings (if still present).
		$out = preg_replace_callback(
			'/&#(x?[0-9a-fA-F]+);/',
			static function ( $m ) {
				$num = $m[1];
				if ( 0 === stripos( $num, 'x' ) ) {
					$code = hexdec( substr( $num, 1 ) );
				} else {
					$code = (int) $num;
				}
				if ( $code <= 0 ) {
					return '';
				}
				if ( function_exists( 'mb_chr' ) ) {
					$ch = mb_chr( $code, 'UTF-8' );
					return is_string( $ch ) ? $ch : '';
				}
				return html_entity_decode( '&#' . $code . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			},
			$out
		);
		$out = str_replace( array( "\r\n", "\r", "\n", "\x0C", "\x0B" ), ' ', (string) $out );
		$out = preg_replace( '/\s+/u', ' ', (string) $out );
		return trim( (string) $out );
	}

	/**
	 * Prefer Arkibas "visning" (web display) over tiny search thumbs.
	 * Thumb and visning often use different file GUIDs, so prefer an explicit visning URL.
	 */
	public static function prefer_display_image( string $thumb_or_any, string $visning = '' ): string {
		$visning = esc_url_raw( $visning );
		if ( $visning && false !== stripos( $visning, '/filer/visning/' ) ) {
			return $visning;
		}
		$url = esc_url_raw( $thumb_or_any );
		if ( ! $url ) {
			return '';
		}
		// Same-GUID upgrade attempt (works for some records).
		$upgraded = preg_replace( '#/filer/thumb/+#i', '/filer/visning/', $url );
		return is_string( $upgraded ) ? $upgraded : $url;
	}

	/**
	 * Billedtekst fra arkiv.dk galleridata (Indhold + evt. Tags.Tekst).
	 *
	 * @param array<string, mixed> $slide One galleridata row.
	 */
	private static function gallery_slide_caption( array $slide ): string {
		$parts = array();

		$indhold = isset( $slide['Indhold'] ) ? self::decode_text( wp_strip_all_tags( (string) $slide['Indhold'] ) ) : '';
		if ( $indhold !== '' ) {
			$parts[] = $indhold;
		}

		if ( ! empty( $slide['Tags'] ) && is_array( $slide['Tags'] ) ) {
			foreach ( $slide['Tags'] as $tag ) {
				if ( ! is_array( $tag ) ) {
					continue;
				}
				$tekst = isset( $tag['Tekst'] ) ? self::decode_text( wp_strip_all_tags( (string) $tag['Tekst'] ) ) : '';
				if ( $tekst !== '' && ! in_array( $tekst, $parts, true ) ) {
					$parts[] = $tekst;
				}
			}
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Parse billedserie: URL + billedtekst per slide.
	 *
	 * @return array<int, array{url: string, caption: string}>
	 */
	public static function parse_galleridata( string $html ): array {
		$rows = array();

		if ( preg_match( '/id="galleridata"[^>]*\s+value="([^"]+)"/i', $html, $match ) ) {
			$raw    = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$slides = json_decode( $raw, true );
			if ( is_array( $slides ) ) {
				foreach ( $slides as $slide ) {
					if ( ! is_array( $slide ) ) {
						continue;
					}
					$azure_id = isset( $slide['AzureVisningID'] )
						? preg_replace( '/[^a-f0-9]/i', '', (string) $slide['AzureVisningID'] )
						: '';
					if ( $azure_id === '' ) {
						continue;
					}
					$url = esc_url_raw( 'https://arkibasapi-apim.azure-api.net/filer/visning/' . $azure_id );
					if ( ! $url ) {
						continue;
					}
					$rows[] = array(
						'url'     => $url,
						'caption' => self::gallery_slide_caption( $slide ),
					);
				}
			}
		}

		if ( empty( $rows ) && preg_match_all(
			'/https:\/\/arkibasapi-apim\.azure-api\.net\/filer\/visning\/[a-f0-9]+/i',
			$html,
			$found
		) ) {
			foreach ( array_values( array_unique( $found[0] ) ) as $url ) {
				$url = esc_url_raw( $url );
				if ( $url ) {
					$rows[] = array(
						'url'     => $url,
						'caption' => '',
					);
				}
			}
		}

		return $rows;
	}

	/**
	 * Extract all visning-URLs from a billedserie (hidden #galleridata JSON).
	 *
	 * @return array<int, string>
	 */
	public static function extract_gallery_images( string $html ): array {
		$urls = array();
		foreach ( self::parse_galleridata( $html ) as $row ) {
			$urls[] = $row['url'];
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * @param mixed $raw Posted value.
	 */
	public static function parse_max_gallery_images( $raw ): int {
		if ( $raw === '' || $raw === null ) {
			return self::DEFAULT_MAX_GALLERY_IMAGES;
		}
		$n = (int) $raw;
		if ( $n < 0 ) {
			return 0;
		}
		return min( 500, $n );
	}

	/**
	 * @param array<int, string> $urls Image URLs.
	 * @return array<int, string>
	 */
	public static function limit_gallery_images( array $urls, int $max ): array {
		$urls = array_values( array_filter( $urls ) );
		if ( $max <= 0 || count( $urls ) <= $max ) {
			return $urls;
		}
		return array_slice( $urls, 0, $max );
	}

	/**
	 * @param array<int, string> $captions Parallel to image URLs.
	 * @return array<int, string>
	 */
	public static function limit_gallery_captions( array $captions, int $max ): array {
		if ( $max <= 0 || count( $captions ) <= $max ) {
			return array_values( $captions );
		}
		return array_slice( $captions, 0, $max );
	}

	/**
	 * Heuristic: billedserie titles on arkiv.dk often mention it in the heading.
	 */
	public static function item_likely_gallery_series( array $item ): bool {
		$title = isset( $item['title'] ) ? (string) $item['title'] : '';
		if ( $title === '' ) {
			return false;
		}
		return (bool) preg_match( '/\b(billedserie|fotoserie|billedserien)\b/iu', $title );
	}

	/**
	 * Count gallery images on arkiv.dk (cached). One HTTP request on cache miss.
	 *
	 * @return array{image_count: int, image?: string}
	 */
	public static function probe_gallery_count( string $arkiv_id ): array {
		$arkiv_id = preg_replace( '/\D+/', '', $arkiv_id );
		if ( $arkiv_id === '' ) {
			return array( 'image_count' => 1 );
		}

		$cache_key = 'th_arkiv_gallery_n_' . $arkiv_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['image_count'] ) ) {
			return $cached;
		}

		$detail = self::fetch_detail( $arkiv_id );
		if ( is_wp_error( $detail ) || ! is_array( $detail ) ) {
			return array( 'image_count' => 1 );
		}

		$count = 1;
		if ( ! empty( $detail['images'] ) && is_array( $detail['images'] ) ) {
			$count = max( 1, count( $detail['images'] ) );
		}

		$out = array( 'image_count' => $count );
		if ( ! empty( $detail['image'] ) ) {
			$out['image'] = (string) $detail['image'];
		}

		set_transient( $cache_key, $out, 7 * DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * Add image_count / import cap hints for the admin preview table.
	 *
	 * @param array<int, array> $items Items.
	 */
	public static function annotate_preview_gallery_meta( array &$items, int $max_gallery, bool $probe_all ): void {
		foreach ( $items as &$item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$probe = $probe_all || self::item_likely_gallery_series( $item );
			$count = 1;
			if ( $probe ) {
				$stats = self::probe_gallery_count( (string) ( $item['arkiv_id'] ?? '' ) );
				$count = max( 1, (int) $stats['image_count'] );
				if ( ! empty( $stats['image'] ) ) {
					$item['image'] = (string) $stats['image'];
				}
			}

			$item['image_count'] = $count;
			$is_series           = self::item_likely_gallery_series( $item ) || $count > 1;
			if ( $is_series ) {
				$item['image_count_import'] = $count;
				$item['images_capped']      = false;
			} elseif ( $max_gallery > 0 && $count > $max_gallery ) {
				$item['image_count_import'] = $max_gallery;
				$item['images_capped']      = true;
			} else {
				$item['image_count_import'] = $count;
				$item['images_capped']      = false;
			}
		}
		unset( $item );
	}

	/**
	 * Fetch a single arkiv.dk detail page and extract display image + cleaned text.
	 *
	 * Mapping:
	 * - title       ← h1 in .registreringheading (opslagets overskrift på arkiv.dk)
	 * - excerpt     ← Beskrivelse (kort tekst på kortet)
	 * - bemaerkning ← Bemærkning (vises i billed-popup)
	 *
	 * @return array{title?:string,excerpt?:string,bemaerkning?:string,image?:string,images?:string[],year_label?:string,year?:int,date?:string}|\WP_Error
	 */
	public static function fetch_detail( string $arkiv_id ) {
		$arkiv_id = preg_replace( '/\D+/', '', $arkiv_id );
		if ( $arkiv_id === '' ) {
			return new WP_Error( 'th_arkiv_id', __( 'Ugyldigt arkiv-id.', 'timeline-history' ) );
		}

		$url = 'https://arkiv.dk/vis/' . rawurlencode( $arkiv_id );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'headers'     => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; TimelineHistoryWordPress/' . TIMELINE_HISTORY_VERSION . '; +https://wordpress.org)',
					'Accept'     => 'text/html,application/xhtml+xml',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 || $body === '' ) {
			return new WP_Error( 'th_arkiv_detail', __( 'Kunne ikke hente registreringen på arkiv.dk.', 'timeline-history' ) );
		}

		$out = array();

		$gallery_rows = self::parse_galleridata( $body );
		if ( ! empty( $gallery_rows ) ) {
			$out['images']         = array_column( $gallery_rows, 'url' );
			$out['imageCaptions']  = array_map(
				static function ( $row ) {
					return isset( $row['caption'] ) ? (string) $row['caption'] : '';
				},
				$gallery_rows
			);
			$out['image'] = $out['images'][0];
		} else {
			if ( preg_match( '/src="(https:\/\/arkibasapi-apim\.azure-api\.net\/filer\/visning\/[^"]+)"/i', $body, $im ) ) {
				$out['image'] = esc_url_raw( html_entity_decode( $im[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			} elseif ( preg_match( '/property="og:image"\s+content="(https:\/\/arkibasapi-apim\.azure-api\.net\/filer\/[^"]+)"/i', $body, $og ) ) {
				$out['image'] = esc_url_raw( html_entity_decode( $og[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}
			if ( ! empty( $out['image'] ) ) {
				$out['images'] = array( $out['image'] );
			}
		}

		$h1 = '';
		if ( preg_match( '/class="registreringheading"[^>]*>\s*<h1[^>]*>\s*(.*?)\s*<\/h1>/is', $body, $hm ) ) {
			$h1 = self::decode_text( wp_strip_all_tags( $hm[1] ) );
		} elseif ( preg_match( '/<h1[^>]*>\s*(.*?)\s*<\/h1>/is', $body, $hm ) ) {
			$h1 = self::decode_text( wp_strip_all_tags( $hm[1] ) );
		}

		$beskrivelse = '';
		if ( preg_match( '/<td>\s*Beskrivelse\s*<\/td>\s*<td>(.*?)<\/td>/is', $body, $dm ) ) {
			$beskrivelse = self::decode_text( wp_strip_all_tags( $dm[1] ) );
		}

		// Title = opslagets h1 (fx "Henrik Wederkinch Tetens, Provst…, Ca.1927"); Beskrivelse kun som fallback.
		$out['title'] = $h1 !== '' ? $h1 : $beskrivelse;

		if ( $beskrivelse !== '' && 0 !== strcasecmp( $beskrivelse, (string) $out['title'] ) ) {
			$out['excerpt'] = $beskrivelse;
		} else {
			$out['excerpt'] = '';
		}

		$bemaerkning = '';
		if ( preg_match( '/<td>\s*Bem(?:æ|&aelig;|&#xE6;|&#230;)rkning\s*<\/td>\s*<td>(.*?)<\/td>/is', $body, $bm ) ) {
			$bemaerkning = self::decode_text( wp_strip_all_tags( $bm[1] ) );
		}
		if ( $bemaerkning !== '' ) {
			$out['bemaerkning'] = $bemaerkning;
		}

		if ( preg_match( '/<td>\s*Periode\s*<\/td>\s*<td>(.*?)<\/td>/is', $body, $pm ) ) {
			$period = self::decode_text( wp_strip_all_tags( $pm[1] ) );
			$out['year_label'] = $period;
			if ( preg_match( '/(\d{4})/', $period, $ym ) ) {
				$out['year'] = (int) $ym[1];
				$out['date'] = sprintf( '%04d-01-01', (int) $ym[1] );
			}
		}

		return $out;
	}

	/**
	 * Merge search-row data with detail-page display image and cleaned text.
	 *
	 * @param array $item Search item.
	 * @return array
	 */
	public static function enrich_item( array $item ): array {
		$item['title']      = self::decode_text( (string) ( $item['title'] ?? '' ) );
		$item['year_label'] = self::decode_text( (string) ( $item['year_label'] ?? '' ) );
		$item['excerpt'] = '';

		$arkiv_id = isset( $item['arkiv_id'] ) ? (string) $item['arkiv_id'] : '';
		if ( $arkiv_id === '' ) {
			$item['image'] = self::prefer_display_image( (string) ( $item['image'] ?? '' ) );
			return $item;
		}

		$detail = self::fetch_detail( $arkiv_id );
		if ( ! is_wp_error( $detail ) && is_array( $detail ) ) {
			if ( ! empty( $detail['images'] ) && is_array( $detail['images'] ) ) {
				$item['images'] = array_values( array_filter( $detail['images'] ) );
				$item['image']  = $item['images'][0] ?? '';
				if ( ! empty( $detail['imageCaptions'] ) && is_array( $detail['imageCaptions'] ) ) {
					$item['imageCaptions'] = array_values( $detail['imageCaptions'] );
				}
			} elseif ( ! empty( $detail['image'] ) ) {
				$item['image']  = $detail['image'];
				$item['images'] = array( $detail['image'] );
			} else {
				$item['image'] = self::prefer_display_image( (string) ( $item['image'] ?? '' ) );
				if ( $item['image'] ) {
					$item['images'] = array( $item['image'] );
				}
			}
			if ( ! empty( $detail['title'] ) ) {
				$item['title'] = $detail['title'];
			}
			$item['excerpt'] = isset( $detail['excerpt'] ) ? (string) $detail['excerpt'] : '';
			$item['bemaerkning'] = isset( $detail['bemaerkning'] ) ? (string) $detail['bemaerkning'] : '';
			if ( ! empty( $detail['year_label'] ) ) {
				$item['year_label'] = $detail['year_label'];
			}
			if ( ! empty( $detail['date'] ) ) {
				$item['date'] = $detail['date'];
			}
			if ( ! empty( $detail['year'] ) ) {
				$item['year'] = (int) $detail['year'];
			}
		} else {
			$item['image'] = self::prefer_display_image( (string) ( $item['image'] ?? '' ) );
		}

		return $item;
	}

	/**
	 * @return array{items: array<int, array>, total: int}
	 */
	public static function parse_html( string $html ): array {
		$total = 0;
		if ( preg_match( '/(\d+)\s+resultater/iu', $html, $m ) ) {
			$total = (int) $m[1];
		}

		$items = array();
		if ( ! preg_match_all( '/<tr\s+data-id="(\d+)"\s+data-href="([^"]+)"[^>]*>(.*?)<\/tr>/is', $html, $rows, PREG_SET_ORDER ) ) {
			return array(
				'items' => array(),
				'total' => $total,
			);
		}

		foreach ( $rows as $row ) {
			$arkiv_id = $row[1];
			$href     = self::decode_text( $row[2] );
			$body     = $row[3];

			$image = '';
			if ( preg_match( '/src="(https:\/\/arkibasapi-apim\.azure-api\.net\/filer\/[^"]+)"/i', $body, $im ) ) {
				$image = html_entity_decode( $im[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}

			$year = '';
			if ( preg_match( '/font-weight:\s*bold;">\s*([^<]+)/i', $body, $ym ) ) {
				$year = self::decode_text( $ym[1] );
			}

			$title = '';
			if ( preg_match( '/class="dotdotdot-truncate"[^>]*>\s*([^<]+)/i', $body, $tm ) ) {
				$title = self::decode_text( $tm[1] );
			}
			if ( $title === '' ) {
				$title = 'Arkiv ' . $arkiv_id;
			}

			$year_int = 0;
			if ( preg_match( '/(\d{4})/', $year, $ym2 ) ) {
				$year_int = (int) $ym2[1];
			}

			$source = 'https://arkiv.dk' . ( 0 === strpos( $href, '/' ) ? $href : '/' . $href );

			$items[] = array(
				'arkiv_id'   => $arkiv_id,
				'title'      => $title,
				'year'       => $year_int,
				'year_label' => $year,
				'date'       => $year_int > 0 ? sprintf( '%04d-01-01', $year_int ) : gmdate( 'Y-m-d' ),
				'image'      => $image,
				'source_url' => $source,
				'excerpt'    => '',
			);
		}

		return array(
			'items' => $items,
			'total' => $total > 0 ? $total : count( $items ),
		);
	}

	/**
	 * Import selected items as timeline_story posts into a named timeline.
	 * Fetches each detail page for display-size images and cleaned text.
	 * Re-import on the same timeline updates image/text. Same arkiv-id on another timeline creates a new story.
	 *
	 * @param array<int, array> $items         Items from parse.
	 * @param string            $timeline_name Timeline display name (created if missing).
	 * @return array{created: int, updated: int, skipped: int, errors: string[], timeline?: array}|array|\WP_Error
	 */
	public static function import_items( array $items, string $timeline_name = '', int $max_gallery_images = 0 ) {
		$timeline_name = sanitize_text_field( trim( $timeline_name ) );
		if ( $timeline_name === '' ) {
			return new WP_Error( 'th_timeline_name', __( 'Angiv et navn til tidslinjen.', 'timeline-history' ) );
		}

		$set = Timeline_History_Sets::ensure( $timeline_name );
		if ( is_wp_error( $set ) ) {
			return $set;
		}

		@set_time_limit( 300 );

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$arkiv_id = isset( $item['arkiv_id'] ) ? sanitize_text_field( (string) $item['arkiv_id'] ) : '';
			if ( $arkiv_id === '' ) {
				continue;
			}

			$enriched = self::enrich_item( $item );
			$existing = self::find_by_arkiv_id( $arkiv_id, $set['slug'] );

			$images = ! empty( $enriched['images'] ) && is_array( $enriched['images'] )
				? array_values( array_filter( $enriched['images'] ) )
				: ( ! empty( $enriched['image'] ) ? array( $enriched['image'] ) : array() );
			$image_total  = count( $images );
			$gallery_cap  = $max_gallery_images;
			if ( $image_total > 1 || self::item_likely_gallery_series( $item ) ) {
				$gallery_cap = 0;
			}
			$images = self::limit_gallery_images( $images, $gallery_cap );

			$captions = array();
			if ( ! empty( $enriched['imageCaptions'] ) && is_array( $enriched['imageCaptions'] ) ) {
				foreach ( $enriched['imageCaptions'] as $cap ) {
					$captions[] = sanitize_textarea_field( self::decode_text( (string) $cap ) );
				}
			}
			$captions = self::limit_gallery_captions( $captions, $gallery_cap );
			while ( count( $captions ) < count( $images ) ) {
				$captions[] = '';
			}
			$captions = array_slice( $captions, 0, count( $images ) );

			$payload = array(
				'title'          => $enriched['title'] ?? '',
				'date'           => $enriched['date'] ?? gmdate( 'Y-m-d' ),
				'excerpt'        => $enriched['excerpt'] ?? '',
				'bemaerkning'    => $enriched['bemaerkning'] ?? '',
				'images'         => $images,
				'imageCaptions'  => $captions,
				'timeline'       => $set['slug'],
			);

			$result = Timeline_History_Story_Repository::upsert(
				$payload,
				$existing ? $existing : null
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = $arkiv_id . ': ' . $result->get_error_message();
				continue;
			}

			$post_id = (int) $result['id'];
			update_post_meta( $post_id, self::ARKIV_ID_KEY, $arkiv_id );
			if ( ! empty( $enriched['source_url'] ) ) {
				update_post_meta( $post_id, self::SOURCE_URL_KEY, esc_url_raw( (string) $enriched['source_url'] ) );
			}
			if ( $image_total > count( $images ) ) {
				update_post_meta( $post_id, self::IMAGE_COUNT_KEY, $image_total );
			} else {
				delete_post_meta( $post_id, self::IMAGE_COUNT_KEY );
			}

			if ( $existing ) {
				$updated++;
			} else {
				$created++;
			}
		}

		return array(
			'created'  => $created,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'errors'   => $errors,
			'timeline' => $set,
		);
	}

	public static function find_by_arkiv_id( string $arkiv_id, string $timeline_slug = '' ): int {
		$args = array(
			'post_type'      => 'timeline_story',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => self::ARKIV_ID_KEY,
			'meta_value'     => $arkiv_id,
		);
		if ( $timeline_slug !== '' ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => Timeline_History_Sets::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( sanitize_title( $timeline_slug ) ),
				),
			);
		}
		$q = new WP_Query( $args );
		return ! empty( $q->posts[0] ) ? (int) $q->posts[0] : 0;
	}
}
