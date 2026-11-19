<?php
/** Standalone LP canvas. Keeps WordPress head/footer and content filters. @package LPG */
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
</head>
<body <?php body_class( 'lpgen-canvas' ); ?>>
<?php wp_body_open(); ?>
<main id="lpgen-main" class="lpgen-canvas__main">
<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
