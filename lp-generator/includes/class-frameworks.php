<?php
/**
 * Copywriting framework presets.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class Frameworks
 */
final class Frameworks {

	/**
	 * Section hints that an AI-generated framework may use.
	 */
	public const HINTS = array( 'hero', 'problem', 'benefits', 'proof', 'process', 'cta', 'faq' );

	/**
	 * Image roles that an AI-generated framework may use.
	 */
	public const IMAGE_ROLES = array( 'hero', 'problem', 'benefit', 'proof', 'process', 'cta', 'faq' );

	/**
	 * Built-in presets. Pure data, safe to unit test.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function presets(): array {
		return array(
			'aida' => array(
				'label'        => 'AIDA',
				'steps'        => array( 'Attention', 'Interest', 'Desire', 'Action' ),
				'section_hint' => array( 'hero', 'problem', 'benefits', 'cta' ),
				'best_for'     => array( 'product' ),
			),
			'pas' => array(
				'label'        => 'PAS',
				'steps'        => array( 'Problem', 'Agitate', 'Solution' ),
				'section_hint' => array( 'pain', 'cost-of-inaction', 'solution', 'cta' ),
				'best_for'     => array( 'service', 'affiliate' ),
			),
			'bab' => array(
				'label'        => 'BAB',
				'steps'        => array( 'Before', 'After', 'Bridge' ),
				'section_hint' => array( 'before', 'after', 'process', 'cta' ),
				'best_for'     => array( 'service', 'affiliate' ),
			),
			'fab' => array(
				'label'        => 'FAB',
				'steps'        => array( 'Features', 'Advantages', 'Benefits' ),
				'section_hint' => array( 'specs', 'advantages', 'benefits', 'cta' ),
				'best_for'     => array( 'product' ),
			),
			'quest' => array(
				'label'        => 'QUEST',
				'steps'        => array( 'Qualify', 'Understand', 'Educate', 'Stimulate', 'Transition', 'Act' ),
				'section_hint' => array( 'qualify', 'pain', 'education', 'offer', 'bridge', 'cta' ),
				'best_for'     => array( 'service' ),
			),
			'dagmar' => array(
				'label'        => 'DAGMAR',
				'steps'        => array( 'Awareness', 'Comprehension', 'Conviction', 'Action' ),
				'section_hint' => array( 'hero', 'explainer', 'proof', 'cta' ),
				'best_for'     => array( 'product', 'service' ),
			),
			'dipada' => array(
				'label'        => 'DIPADA',
				'steps'        => array( 'Desire', 'Interest', 'Persuasion', 'Acceptance', 'Desire', 'Action' ),
				'section_hint' => array( 'outcome', 'interest', 'proof', 'objections', 'offer', 'cta' ),
				'best_for'     => array( 'service' ),
			),
			'idepa' => array(
				'label'        => 'IDEPA',
				'steps'        => array( 'Identification', 'Demonstration', 'Evaluation', 'Prescription', 'Acceptance' ),
				'section_hint' => array( 'identify', 'demo', 'compare', 'recommend', 'cta' ),
				'best_for'     => array( 'product' ),
			),
			'acca' => array(
				'label'        => 'ACCA',
				'steps'        => array( 'Awareness', 'Comprehension', 'Conviction', 'Action' ),
				'section_hint' => array( 'hero', 'explainer', 'testimonials', 'cta' ),
				'best_for'     => array( 'product', 'affiliate' ),
			),
			'aisas' => array(
				'label'        => 'AISAS',
				'steps'        => array( 'Attention', 'Interest', 'Search', 'Action', 'Share' ),
				'section_hint' => array( 'hero', 'benefits', 'proof', 'cta', 'social-proof' ),
				'best_for'     => array( 'product' ),
			),
			'4p' => array(
				'label'        => '4P (Picture – Promise – Prove – Push)',
				'steps'        => array( 'Picture', 'Promise', 'Prove', 'Push' ),
				'section_hint' => array( 'visual', 'promise', 'proof', 'offer' ),
				'best_for'     => array( 'product', 'service' ),
			),
			'spin' => array(
				'label'        => 'SPIN',
				'steps'        => array( 'Situation', 'Problem', 'Implication', 'Need-payoff' ),
				'section_hint' => array( 'context', 'pain', 'cost', 'outcome', 'cta' ),
				'best_for'     => array( 'service' ),
			),
		);
	}

	/**
	 * All presets including saved custom presets.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$presets = self::presets();
		$settings = get_option( 'lpgen_settings', array() );
		$custom   = isset( $settings['custom_frameworks'] ) ? (string) $settings['custom_frameworks'] : '';
		if ( '' === trim( $custom ) ) {
			return $presets;
		}
		$decoded = json_decode( $custom, true );
		if ( ! is_array( $decoded ) ) {
			return $presets;
		}
		foreach ( $decoded as $key => $value ) {
			if ( ! is_string( $key ) || ! is_array( $value ) ) {
				continue;
			}
			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}
			$presets[ $key ] = array(
				'label'        => isset( $value['label'] ) ? sanitize_text_field( (string) $value['label'] ) : $key,
				'steps'        => isset( $value['steps'] ) && is_array( $value['steps'] ) ? array_values( array_map( 'strval', $value['steps'] ) ) : array(),
				'section_hint' => isset( $value['section_hint'] ) && is_array( $value['section_hint'] ) ? array_values( array_map( 'strval', $value['section_hint'] ) ) : array(),
				'best_for'     => isset( $value['best_for'] ) && is_array( $value['best_for'] ) ? array_values( array_map( 'strval', $value['best_for'] ) ) : array(),
			);
		}
		return $presets;
	}

	/**
	 * Sanitize a list of AI-proposed step names.
	 *
	 * @param mixed $raw Raw step names.
	 * @param int   $max Maximum steps.
	 * @return string[]
	 */
	public static function clean_step_names( $raw, int $max = 6 ): array {
		$parts = is_array( $raw ) ? $raw : array();
		$out   = array();
		foreach ( $parts as $part ) {
			if ( is_array( $part ) ) {
				$part = (string) ( $part['name'] ?? '' );
			}
			$name = trim( sanitize_text_field( is_scalar( $part ) ? (string) $part : '' ) );
			if ( '' === $name ) {
				continue;
			}
			$name = Utils::truncate( $name, 60 );
			if ( ! in_array( $name, $out, true ) ) {
				$out[] = $name;
			}
			if ( count( $out ) >= max( 1, min( 6, $max ) ) ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Sanitize one AI-generated framework step.
	 *
	 * @param mixed  $raw           Raw step data.
	 * @param string $fallback_name Name used when AI omits it.
	 * @param int    $index         Zero-based step index.
	 * @return array<string,string>
	 */
	public static function sanitize_step( $raw, string $fallback_name, int $index ): array {
		$data = is_array( $raw ) ? $raw : array();
		$name = trim( sanitize_text_field( (string) ( $data['name'] ?? $fallback_name ) ) );
		if ( '' === $name ) {
			$name = $fallback_name;
		}
		$hint = sanitize_key( (string) ( $data['section_hint'] ?? '' ) );
		if ( ! in_array( $hint, self::HINTS, true ) ) {
			$hint = self::HINTS[ $index % count( self::HINTS ) ];
		}
		$role = sanitize_key( (string) ( $data['image_role'] ?? '' ) );
		if ( ! in_array( $role, self::IMAGE_ROLES, true ) ) {
			$role = $hint;
			if ( ! in_array( $role, self::IMAGE_ROLES, true ) ) {
				$role = self::IMAGE_ROLES[ $index % count( self::IMAGE_ROLES ) ];
			}
		}
		$keyword = trim( sanitize_text_field( (string) ( $data['image_keyword'] ?? '' ) ) );
		return array(
			'name'          => Utils::truncate( $name, 60 ),
			'section_hint'  => $hint,
			'image_role'    => $role,
			'image_keyword' => Utils::truncate( $keyword, 60 ),
		);
	}

	/**
	 * Sanitize a complete generated framework.
	 *
	 * @param mixed $raw Raw framework data or JSON string.
	 * @return array<string,mixed>
	 */
	public static function sanitize_generated( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$label = trim( sanitize_text_field( (string) ( $raw['label'] ?? '' ) ) );
		if ( '' === $label ) {
			$label = 'AI Framework';
		}
		$steps = array();
		$raw_steps = isset( $raw['steps'] ) && is_array( $raw['steps'] ) ? $raw['steps'] : array();
		foreach ( array_slice( $raw_steps, 0, 6 ) as $index => $step ) {
			$fallback = is_array( $step ) ? (string) ( $step['name'] ?? '' ) : ( is_scalar( $step ) ? (string) $step : '' );
			$steps[]  = self::sanitize_step( $step, $fallback, (int) $index );
		}
		if ( empty( $steps ) ) {
			return array();
		}
		return array(
			'label'        => Utils::truncate( $label, 80 ),
			'steps'        => $steps,
			'generated_at' => sanitize_text_field( (string) ( $raw['generated_at'] ?? '' ) ),
		);
	}

	/**
	 * Get one preset with AIDA fallback.
	 *
	 * @param string $key Preset key.
	 * @return array<string,mixed>
	 */
	public static function get( string $key ): array {
		$all = self::all();
		$key = sanitize_key( $key );
		if ( 'ai' === $key ) {
			return array(
				'label'        => __( 'Framework AI Khusus', 'lp-generator' ),
				'steps'        => array( 'Attention', 'Interest', 'Desire', 'Action' ),
				'section_hint' => array( 'hero', 'problem', 'benefits', 'cta' ),
				'best_for'     => array( 'product', 'service', 'affiliate' ),
			);
		}
		if ( isset( $all[ $key ] ) ) {
			return $all[ $key ];
		}
		return self::presets()['aida'];
	}

	/**
	 * Options for a select field.
	 *
	 * @return array<string,string>
	 */
	public static function for_select(): array {
		$options = array();
		foreach ( self::all() as $key => $preset ) {
			$steps = ! empty( $preset['steps'] ) ? implode( ' → ', $preset['steps'] ) : '';
			$label = (string) $preset['label'];
			if ( '' !== $steps && false === strpos( $label, '→' ) && false === strpos( $label, '(' ) ) {
				$label = sprintf( '%s (%s)', $label, $steps );
			}
			$options[ $key ] = $label;
		}
		$options['ai'] = __( 'Framework AI Khusus (Hasil Generate)', 'lp-generator' );
		return $options;
	}
}
