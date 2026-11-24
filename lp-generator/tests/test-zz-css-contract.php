<?php
/** CSS guards for LP canvas layout and dark-surface contrast. */
declare(strict_types=1);
$css_dir = dirname( __DIR__ ) . '/public/css/';
$canvas  = (string) file_get_contents( $css_dir . 'landing-page.css' );
$types   = (string) file_get_contents( $css_dir . 'lp-type-templates.css' );
$models  = (string) file_get_contents( $css_dir . 'lp-models.css' );

lpg_assert_true( false !== strpos( $canvas, '.lpgen-canvas .lpgen-canvas__main { display:block; box-sizing:border-box;' ), 'canvas main tidak overflow karena box-sizing' );
lpg_assert_true( false !== strpos( $types, '.lpg-lpt--service .lpg-lpt__hero h1' ), 'judul hero service menimpa warna gelap' );
lpg_assert_true( false !== strpos( $types, '.lpg-lpt--service .lpg-lpt__contact h2' ), 'judul kontak service menimpa warna gelap' );
lpg_assert_true( false !== strpos( $models, '.lpg-lpm__model { display: none; }' ), 'label model tidak tampil di publik' );
lpg_assert_true( false !== strpos( $models, '.lpg-related__kicker::after' ), 'kicker related lama diganti label netral' );
lpg_assert_true( false !== strpos( $models, '.lpg-lpm__nav-links' ), 'navigasi AIDA tersedia' );
lpg_assert_true( false !== strpos( $models, '.lpg-lpm__breadcrumb' ), 'breadcrumb AIDA tersedia' );
lpg_assert_true( false !== strpos( $types, '.lpg-lpt__hero-aside' ) && false !== strpos( $types, '.lpg-lpt__price-card' ), 'sticky aside dan kartu harga AIDA tersedia' );
