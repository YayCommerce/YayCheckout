<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Name and version of an installed plugin, mu-plugin or theme, read from its
 * file headers — the label a hook-scan row shows for its owner.
 */
class PluginHeaderHelper {

    /**
     * @param  string $kind 'plugin', 'mu-plugin' or 'theme'.
     * @param  string $slug Directory (or single file) name under the root.
     * @param  string $path Absolute path to that directory or file.
     * @return array{name: string, version: string} Falls back to the slug and '' when no header is found.
     */
    public static function describe( string $kind, string $slug, string $path ): array {
        return 'theme' === $kind ? self::theme( $slug, $path ) : self::plugin( $slug, $path );
    }

    /** The first PHP file carrying a plugin header; a single-file plugin or mu-plugin is its own candidate. */
    private static function plugin( string $slug, string $path ): array {
        // get_plugin_data() lives in the admin includes, which a REST request does not load.
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $files = is_dir( $path ) ? glob( $path . '/*.php' ) : [ $path ];

        foreach ( is_array( $files ) ? $files : [] as $file ) {
            $data = get_plugin_data( $file, false, false );
            if ( ! empty( $data['Name'] ) ) {
                return [
                    'name'    => $data['Name'],
                    'version' => (string) $data['Version'],
                ];
            }
        }

        return [
            'name'    => $slug,
            'version' => '',
        ];
    }

    private static function theme( string $slug, string $path ): array {
        // The directory's real casing, not the lowercased slug: on a case-sensitive filesystem WP_Theme would not find Divi/ as divi/.
        $theme = wp_get_theme( basename( $path ), dirname( $path ) );

        return [
            'name'    => $theme->exists() ? (string) $theme->get( 'Name' ) : $slug,
            'version' => $theme->exists() ? (string) $theme->get( 'Version' ) : '',
        ];
    }
}
