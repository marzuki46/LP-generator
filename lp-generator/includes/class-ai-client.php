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
					$clean    = self::strip_fences( (string) ( $response['content'] ?? '' ) );
					$decoded  = json_decode( $clean, true );
					if ( ! is_array( $decoded ) && preg_match( '/(\{[\s\S]*\}|\[[\s\S]*\])/', $clean, $m ) ) {
						$decoded = json_decode( $m[1], true );
					}
					if ( ! is_array( $decoded ) ) {
						throw new \RuntimeException( 'invalid_json_shape: ' . ( function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'decode_failed' ) . ' — ' . Utils::truncate( $clean, 80 ) );
					}
					$required = (array) ( $prompt['required'] ?? array() );
					$missing  = array();
					foreach ( $required as $key ) {
						if ( ! array_key_exists( $key, $decoded ) ) { $missing[] = $key; }
					}
					if ( $missing ) {
						throw new \RuntimeException( 'invalid_json_shape missing=' . implode( ',', $missing ) . ': ' . Utils::truncate( $clean, 80 ) );
					}
					return array(
						'content'    => $clean,
						'model'      => $model,
						'tokens'     => (int) $response['tokens'],
						'raw'        => $clean,
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

	/** Embed texts via 9Router /v1/embeddings; empty result means caller uses lexical fallback. */
	public static function embed( array $texts ): array {
		$settings = get_option( 'lpgen_settings', array() );
		if ( empty( $settings['embedding_enabled'] ) || '' === trim( (string) ( $settings['embedding_model'] ?? '' ) ) ) {
			return array();
		}
		$texts = array_values( array_filter( array_map( 'strval', $texts ) ) );
		if ( ! $texts ) {
			return array();
		}
		$base = rtrim( (string) ( $settings['ninerouter_url'] ?? '' ), '/' );
		$base = (string) preg_replace( '#/v1/?$#i', '', $base );
		$key  = trim( (string) ( $settings['ninerouter_key'] ?? '' ) );
		if ( '' === $base || false === wp_http_validate_url( $base ) ) {
			return array();
		}
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		$response = wp_remote_post(
			$base . '/v1/embeddings',
			array(
				'timeout' => max( 10, min( 120, (int) ( $settings['timeout'] ?? 60 ) ) ),
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'model' => (string) $settings['embedding_model'],
						'input' => $texts,
					)
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Database::log( 'warning', 'Embedding 9Router gagal; memakai fallback leksikal.' );
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return array();
		}
		$vectors = array();
		foreach ( $data['data'] as $row ) {
			if ( is_array( $row ) && isset( $row['embedding'] ) && is_array( $row['embedding'] ) ) {
				$vectors[] = array_map( 'floatval', $row['embedding'] );
			}
		}
		return count( $vectors ) === count( $texts ) ? $vectors : array();
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
		$base = (string) preg_replace( '#/v1/?$#i', '', $base );
		$key  = trim( (string) ( $settings['ninerouter_key'] ?? '' ) );
		if ( '' === $base || false === wp_http_validate_url( $base ) ) {
			throw new \RuntimeException( 'invalid_9router_url' );
		}

		$format  = (string) ( $settings['api_format'] ?? 'openai' );
		$timeout = max( 10, min( 600, (int) ( $settings['timeout'] ?? 120 ) ) );
		$headers = array(
			'Content-Type' => 'application/json',
		);
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		if ( 'anthropic' === $format ) {
			$url  = $base . '/v1/messages';
			$body = array(
				'model'      => $model,
				'max_tokens' => max( 1024, min( 16000, (int) ( $prompt['max_tokens'] ?? 4096 ) ) ),
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
				'temperature' => (float) ( $settings['temperature'] ?? 0.7 ),
				'stream'      => false,
			);
		}

		if ( isset( $prompt['max_tokens'] ) ) { $body['max_tokens'] = max( 1024, min( 16000, (int) $prompt['max_tokens'] ) ); }

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'transport_error: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$err_body = wp_remote_retrieve_body( $response );
			Database::log( 'error', sprintf( 'AI HTTP %d from %s: %s', $code, $url, Utils::truncate( (string) $err_body, 300 ) ) );
			throw new \RuntimeException( 'http_' . $code );
		}
		$raw = (string) wp_remote_retrieve_body( $response );
		$raw = (string) preg_replace( '/^\xEF\xBB\xBF/', '', trim( $raw ) );

		// Handle SSE stream fallback if proxy ignored stream:false
		if ( 0 === strpos( $raw, 'data:' ) || false !== strpos( $raw, "\ndata:" ) ) {
			$lines   = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
			$stream_content = '';
			$stream_tokens  = 0;
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || 0 !== strpos( $line, 'data:' ) ) {
					continue;
				}
				$json_part = trim( substr( $line, 5 ) );
				if ( '[DONE]' === $json_part ) {
					break;
				}
				$chunk = json_decode( $json_part, true );
				if ( is_array( $chunk ) ) {
					$delta = $chunk['choices'][0]['delta']['content'] ?? ( $chunk['choices'][0]['message']['content'] ?? '' );
					$stream_content .= (string) $delta;
					if ( isset( $chunk['usage']['total_tokens'] ) ) {
						$stream_tokens = (int) $chunk['usage']['total_tokens'];
					}
				}
			}
			if ( '' !== trim( $stream_content ) ) {
				$content = self::strip_fences( $stream_content );
				return array(
					'content' => $content,
					'tokens'  => $stream_tokens,
				);
			}
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			Database::log( 'error', sprintf( 'AI non-JSON response from %s (HTTP %d): %s', $model, $code, Utils::truncate( $raw, 300 ) ) );
			throw new \RuntimeException( 'bad_api_json: ' . Utils::truncate( $raw, 100 ) );
		}

		if ( isset( $data['error'] ) ) {
			$err_msg = is_array( $data['error'] ) ? (string) ( $data['error']['message'] ?? wp_json_encode( $data['error'] ) ) : (string) $data['error'];
			Database::log( 'error', sprintf( 'AI API error from %s: %s', $model, $err_msg ) );
			throw new \RuntimeException( 'api_error: ' . Utils::truncate( $err_msg, 120 ) );
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
			if ( isset( $data['choices'][0]['message']['content'] ) && '' !== trim( (string) $data['choices'][0]['message']['content'] ) ) {
				$content = (string) $data['choices'][0]['message']['content'];
			} elseif ( isset( $data['choices'][0]['text'] ) && '' !== trim( (string) $data['choices'][0]['text'] ) ) {
				$content = (string) $data['choices'][0]['text'];
			} elseif ( isset( $data['choices'][0]['message']['reasoning_content'] ) && '' !== trim( (string) $data['choices'][0]['message']['reasoning_content'] ) ) {
				$content = (string) $data['choices'][0]['message']['reasoning_content'];
			} elseif ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
				$content = (string) $data['candidates'][0]['content']['parts'][0]['text'];
			}
			$tokens = (int) ( $data['usage']['total_tokens'] ?? 0 );
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
		$base     = (string) preg_replace( '#/v1/?$#i', '', $base );
		$key      = trim( (string) ( $settings['ninerouter_key'] ?? '' ) );
		if ( '' === $base || false === wp_http_validate_url( $base ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Base URL tidak valid.', 'lp-generator' ),
				'models'  => array(),
			);
		}
		$headers = array();
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		$response = wp_remote_get(
			$base . '/v1/models',
			array(
				'timeout' => 20,
				'headers' => $headers,
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
