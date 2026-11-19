<?php
/**
 * Stable content/image addresses for reviewed LP designs.
 *
 * A design contract is pure data: it tells the AI which section IDs, layouts
 * and image slots exist, and tells the renderer where to put verified content.
 * AI never owns markup, URLs or attachment IDs.
 *
 * @package LPG
 */

declare(strict_types=1);

namespace LPG;

defined( 'ABSPATH' ) || exit;

final class DesignContract {

	public const AIDA = 'aida-1';

	/** All reviewed designs, keyed by framework slug. */
	public static function contracts(): array {
		return array(
			'aida' => array(
				'design_id' => self::AIDA,
				'label'     => 'AIDA',
				'steps'     => array(
					self::step( 'aida-attention', 1, 'Attention', 'hero', 'story', 'aida-hero-visual', 'hero', 'hero', 'aida_hero_image_id' ),
					self::step( 'aida-interest', 2, 'Interest', 'problem', 'story', 'aida-interest-visual', 'problem', 'text', 'aida_interest_image_id' ),
					self::step( 'aida-desire', 3, 'Desire', 'benefits', 'benefits', 'aida-desire-visual', 'benefit', 'benefits', 'aida_desire_image_id' ),
					self::step( 'aida-action', 4, 'Action', 'cta', 'offer', '', 'cta', 'offer', '' ),
				),
			),
			'pas' => array(
				'design_id' => 'pas-1',
				'label'     => 'PAS',
				'steps'     => array(
					self::step( 'pas-problem', 1, 'Problem', 'pain', 'story', 'pas-problem-visual', 'problem', 'text', 'pas_problem_image_id' ),
					self::step( 'pas-agitate', 2, 'Agitate', 'cost-of-inaction', 'story', 'pas-agitate-visual', 'problem', 'text', 'pas_agitate_image_id' ),
					self::step( 'pas-solution', 3, 'Solution', 'solution', 'process', 'pas-solution-visual', 'process', 'process', 'pas_solution_image_id' ),
				),
			),
			'bab' => array(
				'design_id' => 'bab-1',
				'label'     => 'BAB',
				'steps'     => array(
					self::step( 'bab-before', 1, 'Before', 'before', 'story', 'bab-before-visual', 'problem', 'text', 'bab_before_image_id' ),
					self::step( 'bab-after', 2, 'After', 'after', 'benefits', 'bab-after-visual', 'benefit', 'benefits', 'bab_after_image_id' ),
					self::step( 'bab-bridge', 3, 'Bridge', 'process', 'process', 'bab-bridge-visual', 'process', 'process', 'bab_bridge_image_id' ),
				),
			),
			'fab' => array(
				'design_id' => 'fab-1',
				'label'     => 'FAB',
				'steps'     => array(
					self::step( 'fab-features', 1, 'Features', 'specs', 'comparison', 'fab-features-visual', 'proof', 'comparison', 'fab_features_image_id' ),
					self::step( 'fab-advantages', 2, 'Advantages', 'advantages', 'benefits', 'fab-advantages-visual', 'benefit', 'benefits', 'fab_advantages_image_id' ),
					self::step( 'fab-benefits', 3, 'Benefits', 'benefits', 'benefits', 'fab-benefits-visual', 'benefit', 'benefits', 'fab_benefits_image_id' ),
				),
			),
			'quest' => array(
				'design_id' => 'quest-1',
				'label'     => 'QUEST',
				'steps'     => array(
					self::step( 'quest-qualify', 1, 'Qualify', 'qualify', 'story', 'quest-qualify-visual', 'hero', 'text', 'quest_qualify_image_id' ),
					self::step( 'quest-understand', 2, 'Understand', 'pain', 'story', 'quest-understand-visual', 'problem', 'text', 'quest_understand_image_id' ),
					self::step( 'quest-educate', 3, 'Educate', 'education', 'process', 'quest-educate-visual', 'process', 'process', 'quest_educate_image_id' ),
					self::step( 'quest-stimulate', 4, 'Stimulate', 'offer', 'offer', 'quest-stimulate-visual', 'benefit', 'offer', 'quest_stimulate_image_id' ),
					self::step( 'quest-transition', 5, 'Transition', 'bridge', 'process', 'quest-transition-visual', 'process', 'process', 'quest_transition_image_id' ),
					self::step( 'quest-act', 6, 'Act', 'cta', 'offer', '', 'cta', 'offer', '' ),
				),
			),
			'dagmar' => array(
				'design_id' => 'dagmar-1',
				'label'     => 'DAGMAR',
				'steps'     => array(
					self::step( 'dagmar-awareness', 1, 'Awareness', 'hero', 'story', 'dagmar-awareness-visual', 'hero', 'hero', 'dagmar_awareness_image_id' ),
					self::step( 'dagmar-comprehension', 2, 'Comprehension', 'explainer', 'comparison', 'dagmar-comprehension-visual', 'proof', 'comparison', 'dagmar_comprehension_image_id' ),
					self::step( 'dagmar-conviction', 3, 'Conviction', 'proof', 'proof', 'dagmar-conviction-visual', 'proof', 'proof', 'dagmar_conviction_image_id' ),
					self::step( 'dagmar-action', 4, 'Action', 'cta', 'offer', '', 'cta', 'offer', '' ),
				),
			),
			'dipada' => array(
				'design_id' => 'dipada-1',
				'label'     => 'DIPADA',
				'steps'     => array(
					self::step( 'dipada-desire-1', 1, 'Desire', 'outcome', 'benefits', 'dipada-desire-1-visual', 'benefit', 'benefits', 'dipada_desire_1_image_id' ),
					self::step( 'dipada-interest', 2, 'Interest', 'interest', 'story', 'dipada-interest-visual', 'problem', 'text', 'dipada_interest_image_id' ),
					self::step( 'dipada-persuasion', 3, 'Persuasion', 'proof', 'proof', 'dipada-persuasion-visual', 'proof', 'proof', 'dipada_persuasion_image_id' ),
					self::step( 'dipada-acceptance', 4, 'Acceptance', 'objections', 'comparison', 'dipada-acceptance-visual', 'problem', 'comparison', 'dipada_acceptance_image_id' ),
					self::step( 'dipada-desire-2', 5, 'Desire', 'offer', 'offer', 'dipada-desire-2-visual', 'benefit', 'offer', 'dipada_desire_2_image_id' ),
					self::step( 'dipada-action', 6, 'Action', 'cta', 'offer', '', 'cta', 'offer', '' ),
				),
			),
			'idepa' => array(
				'design_id' => 'idepa-1',
				'label'     => 'IDEPA',
				'steps'     => array(
					self::step( 'idepa-identification', 1, 'Identification', 'identify', 'story', 'idepa-identification-visual', 'hero', 'text', 'idepa_identification_image_id' ),
					self::step( 'idepa-demonstration', 2, 'Demonstration', 'demo', 'process', 'idepa-demonstration-visual', 'process', 'process', 'idepa_demonstration_image_id' ),
					self::step( 'idepa-evaluation', 3, 'Evaluation', 'compare', 'comparison', 'idepa-evaluation-visual', 'proof', 'comparison', 'idepa_evaluation_image_id' ),
					self::step( 'idepa-prescription', 4, 'Prescription', 'recommend', 'benefits', 'idepa-prescription-visual', 'benefit', 'benefits', 'idepa_prescription_image_id' ),
					self::step( 'idepa-acceptance', 5, 'Acceptance', 'cta', 'offer', '', 'cta', 'offer', '' ),
				),
			),
			'acca' => array(
				'design_id' => 'acca-1',
				'label'     => 'ACCA',
				'steps'     => array(
					self::step( 'acca-awareness', 1, 'Awareness', 'hero', 'story', 'acca-awareness-visual', 'hero', 'hero', 'acca_awareness_image_id' ),
					self::step( 'acca-comprehension', 2, 'Comprehension', 'explainer', 'comparison', 'acca-comprehension-visual', 'proof', 'comparison', 'acca_comprehension_image_id' ),
					self::step( 'acca-conviction', 3, 'Conviction', 'testimonials', 'proof', 'acca-conviction-visual', 'proof', 'proof', 'acca_conviction_image_id' ),
					self::step( 'acca-action', 4, 'Action', 'cta', 'offer', '', 'cta', 'offer', '' ),
				),
			),
			'aisas' => array(
				'design_id' => 'aisas-1',
				'label'     => 'AISAS',
				'steps'     => array(
					self::step( 'aisas-attention', 1, 'Attention', 'hero', 'story', 'aisas-attention-visual', 'hero', 'hero', 'aisas_attention_image_id' ),
					self::step( 'aisas-interest', 2, 'Interest', 'benefits', 'benefits', 'aisas-interest-visual', 'benefit', 'benefits', 'aisas_interest_image_id' ),
					self::step( 'aisas-search', 3, 'Search', 'proof', 'proof', 'aisas-search-visual', 'proof', 'proof', 'aisas_search_image_id' ),
					self::step( 'aisas-action', 4, 'Action', 'cta', 'offer', '', 'cta', 'offer', '' ),
					self::step( 'aisas-share', 5, 'Share', 'social-proof', 'proof', 'aisas-share-visual', 'proof', 'proof', 'aisas_share_image_id' ),
				),
			),
			'4p' => array(
				'design_id' => '4p-1',
				'label'     => '4P',
				'steps'     => array(
					self::step( '4p-picture', 1, 'Picture', 'visual', 'story', '4p-picture-visual', 'hero', 'hero', '4p_picture_image_id' ),
					self::step( '4p-promise', 2, 'Promise', 'promise', 'benefits', '4p-promise-visual', 'benefit', 'benefits', '4p_promise_image_id' ),
					self::step( '4p-prove', 3, 'Prove', 'proof', 'proof', '4p-prove-visual', 'proof', 'proof', '4p_prove_image_id' ),
					self::step( '4p-push', 4, 'Push', 'offer', 'offer', '', 'cta', 'offer', '' ),
				),
			),
			'spin' => array(
				'design_id' => 'spin-1',
				'label'     => 'SPIN',
				'steps'     => array(
					self::step( 'spin-situation', 1, 'Situation', 'context', 'story', 'spin-situation-visual', 'hero', 'text', 'spin_situation_image_id' ),
					self::step( 'spin-problem', 2, 'Problem', 'pain', 'story', 'spin-problem-visual', 'problem', 'text', 'spin_problem_image_id' ),
					self::step( 'spin-implication', 3, 'Implication', 'cost', 'comparison', 'spin-implication-visual', 'problem', 'comparison', 'spin_implication_image_id' ),
					self::step( 'spin-need-payoff', 4, 'Need-payoff', 'outcome', 'benefits', 'spin-need-payoff-visual', 'benefit', 'benefits', 'spin_need_payoff_image_id' ),
				),
			),
		);
	}

	/** One immutable design slot. */
	private static function step( string $id, int $step, string $name, string $hint, string $layout, string $slot, string $role, string $shape, string $upload_key ): array {
		return array(
			'id'         => $id,
			'step'       => $step,
			'name'       => $name,
			'hint'       => $hint,
			'layout'     => $layout,
			'image_slot' => $slot,
			'role'       => $role,
			'shape'      => $shape,
			'upload_key' => $upload_key,
		);
	}

	public static function definition( string $slug ): array {
		$slug = sanitize_key( $slug );
		return self::contracts()[ $slug ] ?? array();
	}

	/** @return array<int,array<string,mixed>> */
	public static function contract( string $slug ): array {
		$definition = self::definition( $slug );
		return isset( $definition['steps'] ) && is_array( $definition['steps'] ) ? $definition['steps'] : array();
	}

	public static function aida(): array {
		return self::contract( 'aida' );
	}

	public static function id_for( string $slug ): string {
		return (string) ( self::definition( $slug )['design_id'] ?? '' );
	}

	public static function slug_for_id( string $design_id ): string {
		foreach ( self::contracts() as $slug => $definition ) {
			if ( $definition['design_id'] === $design_id ) {
				return (string) $slug;
			}
		}
		return '';
	}

	/** A contract is active only when the campaign plan was bound to the same design. */
	public static function active( array $campaign ): bool {
		$design_id = self::id_for( (string) ( $campaign['framework_slug'] ?? '' ) );
		if ( '' === $design_id ) {
			return false;
		}
		$plan = Frameworks::campaign_plan( $campaign );
		return $design_id === ( $plan['design_id'] ?? '' );
	}

	/**
	 * Bind an AI plan to the reviewed design. Copy stays campaign-specific;
	 * structure and addresses always come from the contract.
	 */
	public static function bind_plan( array $plan, array $campaign ): array {
		$slug     = sanitize_key( (string) ( $campaign['framework_slug'] ?? 'aida' ) );
		$contract = self::contract( $slug );
		if ( ! $contract ) {
			return $plan;
		}
		foreach ( $contract as $index => $slot ) {
			if ( ! isset( $plan['steps'][ $index ] ) || ! is_array( $plan['steps'][ $index ] ) ) {
				continue;
			}
			if ( '' !== $slot['image_slot'] && ! empty( $campaign['image_enabled'] ) && '' === trim( (string) ( $plan['steps'][ $index ]['image_keyword'] ?? '' ) ) ) {
				throw new \RuntimeException( 'Brief gambar untuk slot ' . $slot['id'] . ' belum lengkap.' );
			}
			$plan['steps'][ $index ]['section_hint'] = $slot['hint'];
			$plan['steps'][ $index ]['layout']       = $slot['layout'];
			$plan['steps'][ $index ]['image_role']   = $slot['role'];
			$plan['steps'][ $index ]['section_id']   = $slot['id'];
			$plan['steps'][ $index ]['image_slot']   = $slot['image_slot'];
		}
		$plan['design_id'] = self::id_for( $slug );
		return $plan;
	}

	/**
	 * Apply structural IDs to AI copy. The AI writes copy only; section IDs,
	 * image slots, hints and layouts are deterministic contract data. This keeps
	 * the required AI JSON small and removes a whole class of invalid_shape
	 * failures where a model omitted or mistyped a structural key.
	 */
	public static function bind_content( array $data, array $campaign ): array {
		if ( ! self::active( $campaign ) ) {
			return $data;
		}
		$slug      = sanitize_key( (string) ( $campaign['framework_slug'] ?? '' ) );
		$design_id = self::id_for( $slug );
		$contract  = self::contract( $slug );
		if ( '' === $design_id || ! $contract ) {
			return $data;
		}
		$data['design_id'] = $design_id;
		$sections = isset( $data['sections'] ) && is_array( $data['sections'] ) ? array_values( $data['sections'] ) : array();
		foreach ( $contract as $index => $slot ) {
			if ( ! isset( $sections[ $index ] ) || ! is_array( $sections[ $index ] ) ) {
				continue;
			}
			$sections[ $index ]['step']       = $index + 1;
			$sections[ $index ]['section_id'] = $slot['id'];
			$sections[ $index ]['image_slot'] = $slot['image_slot'];
			$sections[ $index ]['hint']       = $slot['hint'];
			$sections[ $index ]['layout']     = $slot['layout'];
			// AIDA deliberately uses only text, bullets and cards. Strip richer
			// blocks instead of failing the job when a model adds them.
			if ( self::AIDA === $design_id ) {
				unset( $sections[ $index ]['table'], $sections[ $index ]['quote'] );
				if ( in_array( $slot['shape'], array( 'hero', 'offer' ), true ) ) {
					$sections[ $index ]['cards']   = array();
					$sections[ $index ]['bullets'] = array();
				}
				if ( 0 === $index ) {
					$sections[ $index ]['heading'] = (string) ( $data['title'] ?? '' );
					$sections[ $index ]['body']    = (string) ( $data['intro'] ?? '' );
				}
			}
		}
		$data['sections'] = $sections;
		return $data;
	}

	/** Reject AI output that tries to move content or invent image addresses. */
	public static function validate( array $data, array $campaign ): void {
		$slug       = sanitize_key( (string) ( $campaign['framework_slug'] ?? '' ) );
		$design_id  = self::id_for( $slug );
		$contract   = self::contract( $slug );
		if ( '' === $design_id || ! $contract ) {
			throw new \RuntimeException( 'Framework campaign ini tidak memiliki kontrak desain.' );
		}
		if ( ( $data['design_id'] ?? '' ) !== $design_id ) {
			throw new \RuntimeException( 'Kode desain AI tidak sesuai framework campaign.' );
		}
		$sections = $data['sections'] ?? array();
		if ( ! is_array( $sections ) || count( $sections ) !== count( $contract ) ) {
			throw new \RuntimeException( 'Jumlah section AI tidak sesuai kontrak desain.' );
		}
		foreach ( $contract as $index => $slot ) {
			$section = is_array( $sections[ $index ] ?? null ) ? $sections[ $index ] : array();
			$cards   = is_array( $section['cards'] ?? null ) ? $section['cards'] : array();
			$bullets = is_array( $section['bullets'] ?? null ) ? $section['bullets'] : array();
			if ( self::AIDA === $design_id && in_array( $slot['shape'], array( 'hero', 'offer' ), true ) && ( $cards || $bullets ) ) {
				throw new \RuntimeException( 'Hero dan Action AIDA tidak menerima kartu/bullets tambahan.' );
			}
			if ( 'benefits' === $slot['shape'] && ( count( $cards ) < 3 || count( $cards ) > 6 ) ) {
				throw new \RuntimeException( 'Section manfaat memerlukan 3–6 kartu.' );
			}
			if ( self::AIDA === $design_id && ( ! empty( $section['table'] ) || ! empty( $section['quote'] ) ) ) {
				throw new \RuntimeException( 'Desain AIDA ini hanya memakai teks, bullets dan kartu.' );
			}
			if ( ! empty( $section['image_url'] ) || ! empty( $section['images'] ) || ! empty( $data['images'] ) ) {
				throw new \RuntimeException( 'AI tidak boleh memilih URL/attachment gambar; gunakan slot dari plugin.' );
			}
			if ( ( $section['section_id'] ?? '' ) !== $slot['id'] || ( $section['image_slot'] ?? '' ) !== $slot['image_slot'] ) {
				throw new \RuntimeException( 'ID section atau slot gambar AI tidak sesuai kontrak desain.' );
			}
		}
		if ( self::AIDA === $design_id ) {
			$hero_mismatch = ( $sections[0]['heading'] ?? '' ) !== ( $data['title'] ?? '' ) || ( $sections[0]['body'] ?? '' ) !== ( $data['intro'] ?? '' );
			if ( $hero_mismatch ) {
				throw new \RuntimeException( 'Attention harus memakai headline dan intro hero yang sama, bukan section duplikat.' );
			}
		}
	}

	/** Verified image placements for one campaign. */
	public static function placements( array $campaign ): array {
		$slug     = sanitize_key( (string) ( $campaign['framework_slug'] ?? '' ) );
		$contract = self::contract( $slug );
		$plan     = Frameworks::campaign_plan( $campaign );
		$placements = array();
		foreach ( $contract as $index => $slot ) {
			if ( '' === $slot['image_slot'] ) {
				continue;
			}
			$slot['keyword'] = (string) ( $plan['steps'][ $index ]['image_keyword'] ?? '' );
			$slot['role']    = (string) ( $plan['steps'][ $index ]['image_role'] ?? $slot['role'] );
			$placements[]    = $slot;
		}
		return $placements;
	}

	/** Reject unknown, duplicated, stale or cross-campaign images. */
	public static function validate_images( array $images, array $campaign ): array {
		$design_id = self::id_for( (string) ( $campaign['framework_slug'] ?? '' ) );
		$allowed   = array_column( self::placements( $campaign ), null, 'image_slot' );
		$seen      = array();
		foreach ( $images as $image ) {
			$slot_id = (string) ( $image['slot_id'] ?? '' );
			if ( ! isset( $allowed[ $slot_id ] )
				|| isset( $seen[ $slot_id ] )
				|| ( $image['section_id'] ?? '' ) !== $allowed[ $slot_id ]['id']
				|| (int) ( $image['campaign_id'] ?? 0 ) !== (int) ( $campaign['campaign_id'] ?? 0 )
				|| ( $image['design_id'] ?? '' ) !== $design_id
				|| ( $image['campaign_fingerprint'] ?? '' ) !== Frameworks::fingerprint( $campaign ) ) {
				throw new \RuntimeException( 'Gambar ditahan: slot tidak dikenal, ganda, atau berasal dari campaign/desain lain.' );
			}
			$seen[ $slot_id ] = true;
		}
		return $images;
	}
}
