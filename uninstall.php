<?php
/**
 * Removes layouts, yaycheckout_* options and transients, only when the merchant opted in and no
 * other YayCheckout copy (Free or Pro) stays installed.
 *
 * No autoloader here, so the literals below mirror Settings::OPTION_SETTINGS,
 * general.delete_data_on_uninstall and Layout::POST_TYPE. Keep them in sync.
 * Single site only.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$yaycheckout_settings = get_option( 'yaycheckout_settings', [] );

if ( empty( $yaycheckout_settings['general']['delete_data_on_uninstall'] ) ) {
    return;
}

// Free and Pro share these options and layouts, so deleting one copy while another stays installed
// (Pro replacing Free, or the reverse) must keep them for the copy that remains.
if ( ! function_exists( 'get_plugins' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

foreach ( array_keys( get_plugins() ) as $yaycheckout_plugin_file ) {
    if ( 'yaycheckout.php' === basename( $yaycheckout_plugin_file ) && dirname( $yaycheckout_plugin_file ) !== basename( __DIR__ ) ) {
        return;
    }
}

global $wpdb;

// Options first: while the plugin is still loaded (wp plugin uninstall --deactivate), its active-layout
// guard refuses to delete any layout the active-layout option names. Escaped underscore: bare "_" is a
// single-character wildcard. The _transient_ rows are the compatibility catalogue cache.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yaycheckout\_%' OR option_name LIKE '\_transient\_yaycheckout\_%' OR option_name LIKE '\_transient\_timeout\_yaycheckout\_%'"
);
wp_cache_flush();

// Not get_posts(): 'any' excludes trash, where archived layouts live.
$yaycheckout_layout_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'yay_checkout_layout' )
);

foreach ( $yaycheckout_layout_ids as $yaycheckout_layout_id ) {
    wp_delete_post( (int) $yaycheckout_layout_id, true );
}

wp_cache_flush();
