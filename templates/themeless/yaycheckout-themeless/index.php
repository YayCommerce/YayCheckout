<?php
/**
 * Fallback document for a request matched as themeless that CheckoutPage then
 * does not take over: the page's own content in a bare document, so WooCommerce's
 * checkout still renders instead of a blank page.
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main>
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
