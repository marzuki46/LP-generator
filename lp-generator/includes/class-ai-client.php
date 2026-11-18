<?php
/**
 * 9Router chat client with multi-model fallback.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

/**
 * Class AiClient
 */
final class AiClient {

	/**
	 * Generate a structured response with fallback across models.
	 *
	 * @param array $prompt Prompt array from Prompts builder.
	 * @return array{content:string,model:string,tokens:int,raw:string,structured:array}
	 * @throws \RuntimeException When every model fails.
	 */
	public static function generate( array $prompt ): array {
		$settings = get_option( 'lpgen_settings', array() );
		$models   = Utils::parse_model_list( (string) ( $settings['models'] ?? 'vip,mycodex,openai/gpt-4o-mini' ) );
		if ( empty( $models ) ) {
			$models = array( 'vip' );
		}

		$last_error = 'unknown error';
		foreach ( $models as $model ) {
			$attempts = 0;
			while ( $attempts < 2 ) {
				++$attempts;
				try {
					$response = self::request( $model, $prompt, $settings );
					$decoded  = json_decode( $response['content'], true );
					if ( ! is_array( $decoded ) || ! self::has_required_keys( $decoded, (array) ( $prompt['required'] ?? array() ) ) ) {
						throw new \RuntimeException( 'invalid_json_shape' );
					}
					return array(
						'content'    => $response['content'],
						'model'      => $model,
						'tokens'     => (int) $response['tokens'],
						'raw'        => $response['content'],
						'structured' => $decoded,
					);
				} catch ( \RuntimeException $e ) {
					$last_error = $e->getMessage();
					Database::log(
						'warning',
						sprintf( 'AI attempt failed on %s: %s', $model, $last_error ),
						0,
						0,
						array( 'model' => $model, 'attempt' => $attempts )
					);
					if ( 1 === $attempts ) {
						$prompt = Prompts::repair( $prompt, isset( $response['content'] ) ? (string) $response['content'] : '' );
					}
				}
			}
		}
		throw new \RuntimeException( 'AI generation failed: ' . $last_error );
	}

	/**
	 * Perform one HTTP request to 9Router.
	 *
	 * @param string $model    Model or combo id.
	 * @param array  $prompt   Prompt.
	 * @param array  $settings Plugin settings.
	 * @return array{content:string,tokens:int}
	 * @throws \RuntimeException On transport or API error.
	 */
	private static function request( string $model, array $prompt, array $settings ): array {
		$base = rtrim( (string) ( $settings['ninerouter_url'] ?? '' ), '/' );
		$key  = (string) ( $settings['ninerouter_key'] ?? '' );
		if ( '' === $base || false === wp_http_validate_url( $base ) ) {
			throw new \RuntimeException( 'invalid_9router_url' );
		}

		$format  = (string) ( $settings['api_format'] ?? 'openai' );
		$timeout = max( 10, min( 600, (int) ( $settings['timeout'] ?? 120 ) ) );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $key,
		);

		if ( 'anthropic' === $format ) {
			$url  = $base . '/v1/messages';
			$body = array(
				'model'      => $model,
				'max_tokens' => 4096,
				'system'     => (string) ( $prompt['system'] ?? '' ),
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => (string) ( $prompt['user'] ?? '' ),
					),
				),
			);
			$headers['anthropic-version'] = '2023-06-01';
		} else {
			$url  = $base . '/v1/chat/completions';
			$body = array(
				'model'    => $model,
				'messages' => array(
					array(
						'role'    => 'system',
						'content' => (string) ( $prompt['system'] ?? '' ),
					),
					array(
						'role'    => 'user',
						'content' => (string) ( $prompt['user'] ?? '' ),
					),
				),
				'temperature'      => (float) ( $settings['temperature'] ?? 0.7 ),
				'response_format'  => array( 'type' => 'json_object' ),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'transport_error: ' . $response->get_error_code() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'http_' . $code );
		}
		$raw = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'bad_api_json' );
		}

		$content = '';
		$tokens  = 0;
		if ( 'anthropic' === $format ) {
			if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
				foreach ( $data['content'] as $block ) {
					if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
						$content .= (string) $block['text'];
					}
				}
			}
			$tokens = (int) ( $data['usage']['input_tokens'] ?? 0 ) + (int) ( $data['usage']['output_tokens'] ?? 0 );
		} else {
			$content = (string) ( $data['choices'][0]['message']['content'] ?? '' );
			$tokens  = (int) ( $data['usage']['total_tokens'] ?? 0 );
		}

		$content = self::strip_fences( $content );
		if ( '' === trim( $content ) ) {
			throw new \RuntimeException( 'empty_content' );
		}
		return array(
			'content' => $content,
			'tokens'  => $tokens,
		);
	}

	/**
	 * Remove Markdown code fences from a model response.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	public static function strip_fences( string $content ): string {
		$content = trim( $content );
		if ( 0 === strpos( $content, '```' ) ) {
			$content = preg_replace( '/^```(?:json)?\s*/i', '', $content ) ?? $content;
			$content = preg_replace( '/\s*```$/', '', $content ) ?? $content;
		}
		return trim( $content );
	}

	/**
	 * Check required keys.
	 *
	 * @param array $decoded  Decoded JSON.
	 * @param array $required Required keys.
	 * @return bool
	 */
	private static function has_required_keys( array $decoded, array $required ): bool {
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $decoded ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Test the connection by requesting the models list.
	 *
	 * @return array{ok:bool,message:string,models:string[]}
	 */
	public static function test_connection(): array {
		$settings = get_option( 'lpgen_settings', array() );
		$base     = rtrim( (string) ( $settings['ninerouter_url'] ?? '' ), '/' );
		if ( '' === $base || false === wp_http_validate_url( $base ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Base URL tidak valid.', 'lp-generator' ),
				'models'  => array(),
			);
		}
		$response = wp_remote_get(
			$base . '/v1/models',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . (string) ( $settings['ninerouter_key'] ?? '' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
				'models'  => array(),
			);
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ids  = array();
		if ( is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $data['data'] as $model ) {
				if ( isset( $model['id'] ) ) {
					$ids[] = (string) $model['id'];
				}
			}
		}
		return array(
			'ok'      => 200 === (int) wp_remote_retrieve_response_code( $response ),
			'message' => sprintf( __( 'HTTP %d · %d model ditemukan.', 'lp-generator' ), (int) wp_remote_retrieve_response_code( $response ), count( $ids ) ),
			'models'  => $ids,
		);
	}
}
