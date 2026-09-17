<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes layouts and every yaycheckout_* option — but only when the
 * merchant opted in through Settings → General → "Delete all data on
 * uninstall". Off by default so an accidental delete never loses layouts.
 *
 * The plugin autoloader is not loaded here and WooCommerce may be inactive,
 * so the option name, setting key and post type are literals. They mirror
 * Settings::OPTION_SETTINGS, the schema key general.delete_data_on_uninstall
 * and Layout::POST_TYPE — the one documented exception to "storage keys are
 * constants" in docs/code-standards.md. Keep them in sync.
 *
 * Single site only; a network-wide uninstall loop is not implemented.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Read the flag before touching options — the delete below removes it.
$yaycheckout_settings = get_option( 'yaycheckout_settings', [] );

if ( empty( $yaycheckout_settings['general']['delete_data_on_uninstall'] ) ) {
    return;
}

global $wpdb;

// Direct query rather than get_posts(): 'any' excludes trash, and archived
// layouts are stored as trash (LayoutPostStatus::ARCHIVED).
$yaycheckout_layout_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'yay_checkout_layout' )
);

foreach ( $yaycheckout_layout_ids as $yaycheckout_layout_id ) {
    // Force delete: bypass trash so revisions and meta go with the post.
    wp_delete_post( (int) $yaycheckout_layout_id, true );
}

// The escaped underscore matters: an unescaped "_" is a single-character wildcard.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yaycheckout\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

wp_cache_flush();
