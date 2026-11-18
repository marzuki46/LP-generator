<?php
/**
 * Framework preset tests.
 *
 * @package LPG
 */

declare(strict_types=1);

use LPG\Frameworks;

$presets = Frameworks::presets();
lpg_assert_true( count( $presets ) >= 12, 'minimal 12 framework preset' );
lpg_assert_same( array( 'Attention', 'Interest', 'Desire', 'Action' ), $presets['aida']['steps'], 'AIDA steps' );
lpg_assert_same( array( 'Problem', 'Agitate', 'Solution' ), $presets['pas']['steps'], 'PAS steps' );
lpg_assert_true( in_array( 'affiliate', $presets['pas']['best_for'], true ), 'PAS cocok untuk affiliate' );
lpg_assert_same( 'FAB', $presets['fab']['label'], 'FAB label' );
lpg_assert_same( $presets['aida'], Frameworks::get( 'tidak_ada' ), 'fallback ke AIDA' );

$options = Frameworks::for_select();
lpg_assert_true( isset( $options['aida'] ) && false !== strpos( $options['aida'], 'Attention' ), 'AIDA punya ringkasan langkah' );
lpg_assert_true( isset( $options['pas'] ) && false !== strpos( $options['pas'], 'Problem' ), 'PAS punya ringkasan langkah' );
lpg_assert_true( isset( $options['ai'] ), 'opsi framework AI tersedia di select' );
$ai_fallback = Frameworks::get( 'ai' );
lpg_assert_same( 'Framework AI Khusus', $ai_fallback['label'], 'fallback get ai' );
