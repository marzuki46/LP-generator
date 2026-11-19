<?php
/**
 * One immutable visual language for every generated blog image.
 * Topic changes; palette, framing and negative rules never do.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class VisualIdentity {

	public const BACKGROUND = '#1A1A2E';
	public const ACCENT     = '#FF6B35';
	public const GOLD       = '#FFD166';
	public const RATIO_W    = 16;
	public const RATIO_H    = 9;
	public const STYLE      = 'editorial technical illustration';
	public const FILE_TYPE  = 'webp';

	/** Fixed style block. Do not make this topic-dependent. */
	public static function style_block(): string {
		return implode(
			' ',
			array(
				'Create a professional editorial technical illustration for a web development and SEO blog.',
				'Style: clean editorial technology illustration, structured composition, premium SaaS visual language, subtle depth, realistic but simplified, professional and trustworthy.',
				'Brand direction: dark navy background ' . self::BACKGROUND . ', warm orange accents ' . self::ACCENT . ', small gold highlights ' . self::GOLD . ', high contrast, restrained color palette, consistent visual identity.',
				'Composition: 16:9 landscape, clear focal subject in the centre or on the right, generous negative space, suitable for a blog hero image, no clutter.',
				'Mood: professional, calm, modern.',
			)
		);
	}

	public static function negative_block(): string {
		return 'No text, no letters, no fake logos, no watermark. Do not include: unreadable text, random letters, excessive neon, generic stock-photo style, distorted UI elements, long text inside the image.';
	}

	/**
	 * Master prompt: only topic, visual concept and main subject vary.
	 * All campaign strings are sanitised and treated as untrusted data.
	 */
	public static function master_prompt( string $topic, string $concept, string $subject ): string {
		$clean = static function ( string $value, int $length ): string {
			return Utils::truncate( sanitize_text_field( $value ), $length );
		};
		// Only Topic, Visual concept and Main subject change. Everything below
		// is the locked master prompt and must stay identical for every article.
		return "Create a professional editorial technical illustration for a web development and SEO blog.\n\n"
			. 'Topic: ' . $clean( $topic, 160 ) . "\n\n"
			. 'Visual concept: ' . $clean( $concept, 300 ) . "\n\n"
			. 'Main subject: ' . $clean( $subject, 120 ) . "\n\n"
			. "Style:\n"
			. "clean editorial technology illustration, structured composition,\n"
			. "premium SaaS visual language, subtle depth, realistic but simplified,\n"
			. "professional and trustworthy.\n\n"
			. "Brand direction:\n"
			. 'dark navy background ' . self::BACKGROUND . ', warm orange accents ' . self::ACCENT . ', small gold highlights ' . self::GOLD . ",\n"
			. "high contrast, restrained color palette, consistent visual identity.\n\n"
			. "Composition:\n"
			. "16:9 landscape, clear focal subject in the centre or on the right, generous negative space,\n"
			. "suitable for a blog hero image, no clutter.\n\n"
			. "Do not include:\n"
			. "No text, no letters, no fake logos, no watermark. Unreadable text, random letters,\n"
			. 'excessive neon, generic stock-photo style, distorted UI elements.';
	}

	/** Canonical 16:9 dimensions for the configured width. */
	public static function dimensions( int $width = 1280 ): array {
		$width = max( 640, min( 2560, $width ) );
		return array( $width, max( 1, (int) round( $width * self::RATIO_H / self::RATIO_W ) ) );
	}

	/** Crop rectangle that covers the source while preserving 16:9. */
	public static function crop_size( int $width, int $height, int $max_width ): array {
		$width    = max( 1, $width );
		$height   = max( 1, $height );
		$max_width = max( 320, $max_width );
		$ratio    = self::RATIO_W / self::RATIO_H;
		$crop_w   = $width;
		$crop_h   = (int) round( $width / $ratio );
		if ( $crop_h > $height ) {
			$crop_h = $height;
			$crop_w = (int) round( $height * $ratio );
		}
		$target_w = min( $max_width, $crop_w );
		$target_h = max( 1, (int) round( $target_w / $ratio ) );
		return array(
			'x'         => max( 0, (int) round( ( $width - $crop_w ) / 2 ) ),
			'y'         => max( 0, (int) round( ( $height - $crop_h ) / 2 ) ),
			'width'     => max( 1, $crop_w ),
			'height'    => max( 1, $crop_h ),
			'target_w'  => max( 1, $target_w ),
			'target_h'  => max( 1, $target_h ),
		);
	}

	/** Logical export folder: content/<article-slug>/. */
	public static function folder( string $slug ): string {
		$slug = trim( (string) sanitize_title( $slug ), '/' );
		return 'content/' . ( '' !== $slug ? $slug : 'article' );
	}

	/** Descriptive WebP name, e.g. n8n-workflow-automation-business-process.webp. */
	public static function filename( string $slug, string $type = 'hero' ): string {
		$slug = trim( (string) sanitize_title( $slug ), '/' );
		if ( '' === $slug ) {
			$slug = 'article';
		}
		$type = trim( (string) sanitize_title( $type ), '/' );
		$name = '' !== $type && 'hero' !== $type ? $slug . '-' . $type : $slug;
		return self::folder( $slug ) . '/' . $name . '.' . self::FILE_TYPE;
	}

	/** Stable basename used for WordPress attachment files. */
	public static function basename( string $slug, string $type = 'hero' ): string {
		$path = self::filename( $slug, $type );
		return sanitize_file_name( pathinfo( $path, PATHINFO_FILENAME ) );
	}
}
