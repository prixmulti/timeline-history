<?php
/**
 * Udled farvehints fra aktivt WordPress-tema (block + klassisk).
 *
 * @package TimelineHistory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeline_History_Theme_Adapt {

	/**
	 * @return array<string, string> Keys: pageBg, cardBg, line, accent, text, muted (hex).
	 */
	public static function color_hints(): array {
		$colors = array();

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings();
			$palette  = $settings['color']['palette'] ?? array();
			if ( isset( $settings['color']['palette']['theme'] ) && is_array( $settings['color']['palette']['theme'] ) ) {
				$palette = $settings['color']['palette']['theme'];
			}

			$slug_map = array(
				'primary'    => 'accent',
				'secondary'  => 'line',
				'tertiary'   => 'line',
				'base'       => 'accent',
				'contrast'   => 'text',
				'foreground' => 'text',
				'background' => 'pageBg',
			);

			foreach ( $palette as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['slug'] ) || empty( $entry['color'] ) ) {
					continue;
				}
				$slug = (string) $entry['slug'];
				if ( ! isset( $slug_map[ $slug ] ) ) {
					continue;
				}
				$key = $slug_map[ $slug ];
				if ( isset( $colors[ $key ] ) ) {
					continue;
				}
				$hex = self::normalize_color( (string) $entry['color'] );
				if ( $hex ) {
					$colors[ $key ] = $hex;
				}
			}

			if ( empty( $colors['pageBg'] ) && ! empty( $settings['color']['background'] ) ) {
				$hex = self::normalize_color( (string) $settings['color']['background'] );
				if ( $hex ) {
					$colors['pageBg'] = $hex;
				}
			}
		}

		$bg_mod = get_theme_mod( 'background_color', '' );
		if ( $bg_mod && empty( $colors['pageBg'] ) ) {
			$hex = self::normalize_color( '#' . ltrim( (string) $bg_mod, '#' ) );
			if ( $hex ) {
				$colors['pageBg'] = $hex;
			}
		}

		$header = get_theme_mod( 'header_textcolor', '' );
		if ( $header && $header !== 'blank' && empty( $colors['accent'] ) ) {
			$hex = self::normalize_color( '#' . ltrim( (string) $header, '#' ) );
			if ( $hex ) {
				$colors['accent'] = $hex;
			}
		}

		$text_mod = get_theme_mod( 'text_color', '' );
		if ( $text_mod && empty( $colors['text'] ) ) {
			$hex = self::normalize_color( '#' . ltrim( (string) $text_mod, '#' ) );
			if ( $hex ) {
				$colors['text'] = $hex;
			}
		}

		return $colors;
	}

	/**
	 * @param array<string, mixed> $config Timeline_History_Settings::to_config() shape.
	 * @return array<string, mixed>
	 */
	public static function payload_config( array $config ): array {
		if ( ( $config['themeAdapt'] ?? 'auto' ) === 'off' ) {
			return $config;
		}
		if ( ! isset( $config['themeAdapt'] ) ) {
			$config['themeAdapt'] = 'auto';
		}
		return $config;
	}

	/**
	 * @param array<string, mixed> $config
	 * @return array<string, mixed>
	 */
	public static function embed_payload( array $config, array $stories ): array {
		$config = self::payload_config( $config );
		$out    = array(
			'config'  => $config,
			'stories' => $stories,
		);
		if ( ( $config['themeAdapt'] ?? 'auto' ) !== 'off' ) {
			$hints = self::color_hints();
			if ( ! empty( $hints ) ) {
				$out['themeHints'] = $hints;
			}
		}
		return $out;
	}

	private static function normalize_color( string $color ): string {
		$hex = sanitize_hex_color( $color );
		return is_string( $hex ) ? $hex : '';
	}
}
