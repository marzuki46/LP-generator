<?php
/**
 * Minimal WP stubs so pure helpers can be tested outside WordPress.
 *
 * @package LPG
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/fake-wp/' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); } }
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return (string) $text;
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return (string) $text;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $text ) {
		return rtrim( (string) $text, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $text ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $text ) ), '-' );
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ) {
		return trim( preg_replace( '/[^A-Za-z0-9._-]+/', '-', (string) $name ), '-' );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['lpg_test_options'][$name] ?? $default;
	}
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		if ( 'timestamp' === $type ) {
			return time();
		}
		return gmdate( 'mysql' === $type ? 'Y-m-d H:i:s' : $type );
	}
}
if ( ! function_exists( 'get_gmt_from_date' ) ) {
	function get_gmt_from_date( $string, $format = 'Y-m-d H:i:s' ) {
		$timestamp = strtotime( (string) $string . ' UTC' );
		return gmdate( $format, false === $timestamp ? time() : $timestamp );
	}
}
if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return random_int( $min, $max );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL );
	}
}
if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( $post_id, $thumbnail_id ) {
		$GLOBALS['lpg_thumbnails'][ (int) $post_id ] = (int) $thumbnail_id;
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-research-source.php';
require_once dirname( __DIR__ ) . '/includes/class-frameworks.php';
require_once dirname( __DIR__ ) . '/includes/class-design-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-prompts.php';
require_once dirname( __DIR__ ) . '/includes/class-amazon.php';
require_once dirname( __DIR__ ) . '/includes/class-image.php';
require_once dirname( __DIR__ ) . '/includes/class-indexer.php';
require_once dirname( __DIR__ ) . '/includes/class-topic-memory.php';
require_once dirname( __DIR__ ) . '/includes/class-funnel-content.php';
require_once dirname( __DIR__ ) . '/includes/class-cognitive-engine.php';
require_once dirname( __DIR__ ) . '/includes/class-structured-output.php';
require_once dirname( __DIR__ ) . '/includes/class-orchestrator.php';
require_once dirname( __DIR__ ) . '/includes/class-visual-identity.php';
require_once dirname( __DIR__ ) . '/includes/class-landing-page.php';
require_once dirname( __DIR__ ) . '/includes/class-aida-layout.php';
require_once dirname( __DIR__ ) . '/includes/class-seo-standards.php';
require_once dirname( __DIR__ ) . '/includes/class-publisher.php';
require_once dirname( __DIR__ ) . '/includes/class-updater.php';
require_once dirname( __DIR__ ) . '/includes/class-ai-client.php';
