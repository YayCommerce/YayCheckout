<?php
/**
 * WordPress test-library config. Every value is overridable from the
 * environment so nothing site-specific is committed; see phpunit.xml.dist.
 */

// The Local site whose core files the tests run against: the nearest directory above tests/ holding
// wp-load.php, so a checkout in wp-content/plugins/ and a worktree under it (.claude/worktrees/<slug>/) both find it.
$yaycheckout_abspath = getenv( 'WP_TESTS_ABSPATH' );
if ( ! $yaycheckout_abspath ) {
    $yaycheckout_abspath = dirname( __DIR__, 4 );
    for ( $yaycheckout_dir = dirname( __DIR__ ); dirname( $yaycheckout_dir ) !== $yaycheckout_dir; $yaycheckout_dir = dirname( $yaycheckout_dir ) ) {
        if ( is_file( $yaycheckout_dir . '/wp-load.php' ) ) {
            $yaycheckout_abspath = $yaycheckout_dir;
            break;
        }
    }
}
define( 'ABSPATH', rtrim( $yaycheckout_abspath, '/' ) . '/' );

// The site's mu-plugins would load too; a missing directory makes wp_get_mu_plugins() return nothing.
// Drop-ins (object-cache.php, db.php) under the site's wp-content still load.
define( 'WPMU_PLUGIN_DIR', __DIR__ . '/no-mu-plugins' );

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ?: 'yaycheckout_tests' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ?: 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'YayCheckout Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
