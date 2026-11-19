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
	public const HINTS = array( 'hero', 'problem', 'benefits', 'proof', 'process', 'cta', 'faq', 'pain', 'cost-of-inaction', 'solution', 'before', 'after', 'specs', 'advantages', 'qualify', 'education', 'offer', 'bridge', 'explainer', 'outcome', 'interest', 'objections', 'identify', 'demo', 'compare', 'recommend', 'testimonials', 'social-proof', 'visual', 'promise', 'context', 'cost' );

	/**
	 * Image roles that an AI-generated framework may use.
	 */
	public const IMAGE_ROLES = array( 'hero', 'problem', 'benefit', 'proof', 'process', 'cta', 'faq' );

	/**
	 * Built-in presets. Pure data, safe to unit test.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public const LAYOUTS = array( 'story', 'benefits', 'process', 'comparison', 'proof', 'offer' );

	public static function layout_for_hint( string $hint ): string {
		if ( in_array( $hint, array( 'benefits', 'advantages', 'after', 'outcome', 'promise' ), true ) ) { return 'benefits'; }
		if ( in_array( $hint, array( 'process', 'bridge', 'education', 'demo' ), true ) ) { return 'process'; }
		if ( in_array( $hint, array( 'compare', 'specs', 'explainer' ), true ) ) { return 'comparison'; }
		if ( in_array( $hint, array( 'proof', 'testimonials', 'social-proof' ), true ) ) { return 'proof'; }
		if ( in_array( $hint, array( 'cta', 'offer', 'recommend' ), true ) ) { return 'offer'; }
		return 'story';
	}

	/** Bind the plan to its campaign and the offer data used to design it. */
	public static function fingerprint( array $context ): string {
		$data = array();
		foreach ( array( 'campaign_id', 'offer_type', 'offer_name', 'offer_url', 'audience', 'problem', 'promise', 'benefits', 'price', 'bonus', 'guarantee', 'cta', 'voice', 'language', 'phone', 'brand', 'city', 'target_areas' ) as $key ) {
			$data[ $key ] = $context[ $key ] ?? '';
		}
		$data['basis'] = (string) ( $context['framework_slug'] ?? 'aida' );
		$design_id = DesignContract::id_for( $data['basis'] );
		if ( '' !== $design_id ) { $data['design_id'] = $design_id; }
		$upload_keys = array_column( DesignContract::contract( $data['basis'] ), 'upload_key' );
		foreach ( array_filter( $upload_keys ) as $key ) { $data[ $key ] = absint( $context[ $key ] ?? 0 ); }
		return hash( 'sha256', (string) wp_json_encode( $data ) );
	}

	public static function campaign_plan( array $context ): array {
		$plan = self::sanitize_generated( $context['ai_framework'] ?? '' );
		if ( empty( $plan['steps'] ) || $plan['campaign_id'] !== (int) ( $context['campaign_id'] ?? 0 ) || ! hash_equals( self::fingerprint( $context ), $plan['fingerprint'] ) ) { return array(); }
		return $plan;
	}

	/** Never silently replace a selected formula with AIDA or remove repeated steps. */
	public static function validate_plan( array $raw, array $context ): array {
		$basis = (string) ( $context['framework_slug'] ?? 'aida' );
		$steps = $raw['steps'] ?? null;
		if ( ! is_array( $steps ) || count( $steps ) < 3 || count( $steps ) > 12 ) { throw new \RuntimeException( 'Framework AI harus memiliki 3–12 langkah yang lengkap.' ); }
		$expected = 'ai' === $basis ? array() : (array) self::get( $basis )['steps'];
		if ( $expected && count( $steps ) !== count( $expected ) ) { throw new \RuntimeException( 'Jumlah langkah AI tidak sesuai framework pilihan.' ); }
		if ( 'ai' === $basis && ( count( $steps ) < 4 || count( $steps ) > 6 ) ) { throw new \RuntimeException( 'Framework AI khusus memerlukan 4–6 langkah.' ); }
		$contract = DesignContract::contract( $basis );
		foreach ( $steps as $index => $step ) {
			if ( is_array( $step ) && isset( $contract[ $index ] ) ) {
				$step['section_hint'] = $contract[ $index ]['hint'];
				$step['layout']       = $contract[ $index ]['layout'];
				$steps[ $index ]      = $step;
			}
		}
		$raw['steps'] = $steps;
		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) || ! is_string( $step['name'] ?? null ) || '' === trim( $step['name'] ) || ! is_string( $step['heading_goal'] ?? null ) || '' === trim( $step['heading_goal'] ) || ! in_array( $step['section_hint'] ?? '', self::HINTS, true ) || ! in_array( $step['layout'] ?? '', self::LAYOUTS, true ) ) { throw new \RuntimeException( 'Langkah framework AI belum memiliki tujuan dan layout yang valid.' ); }
			if ( $expected && trim( $step['name'] ) !== $expected[ $index ] ) { throw new \RuntimeException( 'Urutan/nama langkah harus mengikuti framework yang dipilih.' ); }
		}
		$raw['basis'] = $basis;
		$raw['campaign_id'] = (int) ( $context['campaign_id'] ?? 0 );
		$raw['fingerprint'] = self::fingerprint( $context );
		$raw['generated_at'] = current_time( 'mysql' );
		return self::sanitize_generated( DesignContract::bind_plan( $raw, $context ) );
	}

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
			'layout' => in_array( (string) ( $data['layout'] ?? '' ), self::LAYOUTS, true ) ? $data['layout'] : self::layout_for_hint( $hint ),
			'section_id' => sanitize_key( (string) ( $data['section_id'] ?? '' ) ),
			'image_slot' => sanitize_key( (string) ( $data['image_slot'] ?? '' ) ),
			'heading_goal' => Utils::truncate( sanitize_text_field( (string) ( $data['heading_goal'] ?? '' ) ), 300 ),
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
		foreach ( array_slice( $raw_steps, 0, 12 ) as $index => $step ) {
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
			'design_id' => sanitize_key( (string) ( $raw['design_id'] ?? '' ) ),
			'basis' => sanitize_key( (string) ( $raw['basis'] ?? '' ) ),
			'campaign_id' => absint( $raw['campaign_id'] ?? 0 ),
			'fingerprint' => sanitize_text_field( (string) ( $raw['fingerprint'] ?? '' ) ),
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
