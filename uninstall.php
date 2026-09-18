<?php
/**
 * Removes layouts and yaycheckout_* options, only when the merchant opted in.
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

global $wpdb;

// Not get_posts(): 'any' excludes trash, where archived layouts live.
$yaycheckout_layout_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'yay_checkout_layout' )
);

foreach ( $yaycheckout_layout_ids as $yaycheckout_layout_id ) {
    wp_delete_post( (int) $yaycheckout_layout_id, true );
}

// Escaped underscore: bare "_" is a single-character wildcard.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yaycheckout\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

wp_cache_flush();
