<?php
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php if ( ! current_theme_supports( 'title-tag' ) ) : ?><title><?php echo esc_html( wp_get_document_title() ); ?></title><?php endif; ?><?php wp_head(); ?></head>
<body <?php body_class( 'prm-canvas-page' ); ?>>
<?php wp_body_open(); ?>
<?php
if ( is_home() ) {
    echo do_shortcode( '[parmis_mag]' );
} else {
    while ( have_posts() ) {
        the_post();
        the_content();
    }
}
wp_footer();
?>
</body></html>
