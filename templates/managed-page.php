<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="h2osm-site" class="h2osm-site" role="main">
    <?php
    while ( have_posts() ) {
        the_post();
        the_content();
    }
    ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
