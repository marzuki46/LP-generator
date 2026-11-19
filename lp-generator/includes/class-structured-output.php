<?php
/**
 * Instructor-style structured output validation with bounded re-ask.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class StructuredOutput {

	/** Topic-stage schema used before an article is written. */
	public static function topic_schema(): array {
		return array(
			'title'             => array( 'type' => 'string', 'required' => true, 'min' => 8, 'max' => 140 ),
			'angle'             => array( 'type' => 'string', 'required' => true, 'min' => 10 ),
			'intent'            => array( 'type' => 'enum', 'required' => true, 'values' => array( 'informational', 'commercial', 'transactional' ) ),
			'keywords'          => array( 'type' => 'array', 'required' => true, 'min' => 3, 'max' => 6 ),
			'outline'           => array( 'type' => 'array', 'required' => true, 'min' => 4, 'max' => 6 ),
			'pain_points'       => array( 'type' => 'array', 'required' => true, 'min' => 3, 'max' => 5 ),
			'target_table'      => array( 'type' => 'table_or_null', 'required' => true ),
			'funnel_link_angle' => array( 'type' => 'string', 'required' => true, 'min' => 10 ),
		);
	}

	/** Article-stage schema; structural IDs are injected by plugins, not AI. */
	public static function article_schema(): array {
		return array(
			'title'            => array( 'type' => 'string', 'required' => true, 'min' => 8 ),
			'seo_title'        => array( 'type' => 'string', 'required' => true, 'max' => 60 ),
			'meta_description' => array( 'type' => 'string', 'required' => true, 'max' => 155 ),
			'intro'            => array( 'type' => 'string', 'required' => true, 'min' => 40 ),
			'sections'         => array( 'type' => 'array', 'required' => true, 'min' => 3, 'max' => 7 ),
			'cta_paragraph'    => array( 'type' => 'string', 'required' => true, 'min' => 30 ),
			'anchor_suggestion' => array( 'type' => 'string', 'required' => true, 'min' => 6 ),
			'faq'              => array( 'type' => 'array', 'required' => true, 'min' => 3 ),
			'tags'             => array( 'type' => 'array', 'required' => true, 'min' => 1 ),
		);
	}

	/** Validate decoded JSON against a small declarative schema. */
	public static function validate( array $data, array $schema ): array {
		$errors = array();
		foreach ( $schema as $field => $rule ) {
			$exists = array_key_exists( $field, $data );
			if ( ! $exists ) {
				if ( ! empty( $rule['required'] ) ) { $errors[] = $field . ': required'; }
				continue;
			}
			$value = $data[ $field ];
			$type  = (string) ( $rule['type'] ?? 'string' );
			if ( 'string' === $type && ! is_string( $value ) ) {
				$errors[] = $field . ': must be string';
				continue;
			}
			if ( 'array' === $type && ! is_array( $value ) ) {
				$errors[] = $field . ': must be array';
				continue;
			}
			if ( 'enum' === $type && ! in_array( (string) $value, (array) ( $rule['values'] ?? array() ), true ) ) {
				$errors[] = $field . ': value not allowed';
				continue;
			}
			if ( 'table_or_null' === $type && null !== $value && ( ! is_array( $value ) || ! isset( $value['columns'], $value['rows'] ) || ! is_array( $value['columns'] ) || ! is_array( $value['rows'] ) ) ) {
				$errors[] = $field . ': must be {columns, rows} or null';
				continue;
			}
			if ( is_string( $value ) && isset( $rule['min'] ) && mb_strlen( trim( $value ) ) < (int) $rule['min'] ) {
				$errors[] = $field . ': too short';
			}
			if ( is_string( $value ) && isset( $rule['max'] ) && mb_strlen( trim( $value ) ) > (int) $rule['max'] ) {
				$errors[] = $field . ': too long';
			}
			if ( is_array( $value ) && isset( $rule['min'] ) && count( $value ) < (int) $rule['min'] ) {
				$errors[] = $field . ': need at least ' . (int) $rule['min'] . ' items';
			}
			if ( is_array( $value ) && isset( $rule['max'] ) && count( $value ) > (int) $rule['max'] ) {
				$errors[] = $field . ': too many items';
			}
		}
		return $errors;
	}

	/** Reject quotes that are not present in the user-supplied source records. */
	public static function verify_quotes( array $data, array $sources ): array {
		$errors = array();
		$walk   = function ( $value ) use ( &$walk, &$errors, $sources ): void {
			if ( is_array( $value ) ) {
				foreach ( $value as $key => $item ) {
					if ( 'exact_quote' === (string) $key && is_string( $item ) && '' !== trim( $item ) && ! ResearchSource::quote_exists( $item, $sources ) ) {
						$errors[] = 'exact_quote: not found in supplied sources';
					}
					$walk( $item );
				}
			}
		};
		$walk( $data );
		return array_values( array_unique( $errors ) );
	}

	/**
	 * Generate and validate with a bounded, error-aware re-ask.
	 *
	 * @return array AiClient-shaped result plus attempts/validation_errors.
	 */
	public static function generate_validated( array $prompt, array $schema, array $sources = array(), int $max_retries = 1 ): array {
		$max_retries = max( 0, min( 3, $max_retries ) );
		$attempt     = 0;
		$errors      = array();
		do {
			++$attempt;
			$result = AiClient::generate( $prompt );
			$errors = array_merge( self::validate( $result['structured'], $schema ), self::verify_quotes( $result['structured'], $sources ) );
			if ( ! $errors || $attempt > $max_retries ) {
				break;
			}
			$repair        = Prompts::repair( $prompt, wp_json_encode( $result['structured'] ) );
			$repair['user'] .= "\n\nVALIDATION ERRORS (fix exactly these, then return only JSON):\n- " . implode( "\n- ", $errors );
			$prompt = $repair;
		} while ( true );
		$result['attempts']          = $attempt;
		$result['validation_errors'] = $errors;
		return $result;
	}
}
