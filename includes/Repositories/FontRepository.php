<?php
namespace YayCheckout\Repositories;

use YayCheckout\Helpers\Logger;
use YayCheckout\Models\LayoutDesign;

defined( 'ABSPATH' ) || exit;

/**
 * The design fonts in WordPress's Font Library. A picked font is installed the way the Site Editor installs one
 * from Google Fonts: its files are downloaded into wp_get_font_dir() and stored as a wp_font_family post with a
 * wp_font_face post per file, so it is listed under Appearance → Fonts and core deletes its files with it.
 * It is not activated in the site's global styles: only the checkout prints it (Renderer\DesignFonts).
 */
class FontRepository {

    /** Core's Google Fonts collection, which lists every font in LayoutDesign::FONT_FAMILIES under the same slug. */
    private const COLLECTION = 'google-fonts';

    /** The weights checkout-base.css uses; a font without one of them falls back to a synthesized or nearer weight. */
    private const WEIGHTS = [ '400', '500', '600', '700' ];

    /**
     * Whether the theme or the site's global styles already load the font, so the checkout prints no faces of its own.
     * A LayoutDesign::FONT_STACKS font always is, since the shopper's device has it: nothing installs or loads it.
     *
     * @param string $slug A LayoutDesign::FONT_FAMILIES key other than ''.
     */
    public function is_provided_by_site( string $slug ): bool {
        if ( isset( LayoutDesign::FONT_STACKS[ $slug ] ) ) {
            return true;
        }

        $name     = strtolower( LayoutDesign::FONT_FAMILIES[ $slug ] );
        $families = wp_get_global_settings( [ 'typography', 'fontFamilies' ] );

        foreach ( is_array( $families ) ? $families : [] as $origin ) {
            foreach ( is_array( $origin ) ? $origin : [] as $family ) {
                if ( empty( $family['fontFace'] ) || empty( $family['fontFamily'] ) ) {
                    continue;
                }

                // `fontFamily` is a stack such as `"Open Sans", sans-serif`; its first name is the loaded one.
                $first = strtolower( trim( explode( ',', (string) $family['fontFamily'] )[0], " \t\"'" ) );

                if ( $name === $first || ( isset( $family['slug'] ) && $slug === $family['slug'] ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The installed faces of a font, from its Font Library family: each face's settings and the URL of its file.
     * A face without a stored file (one added by URL) is skipped, since its URL may point anywhere.
     *
     * @return array<int, array{style: string, weight: string, url: string}>
     */
    public function faces( string $slug ): array {
        $family_id = $this->family_id( $slug );

        if ( 0 === $family_id ) {
            return [];
        }

        $posts = get_posts(
            [
                'post_type'      => 'wp_font_face',
                'post_parent'    => $family_id,
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]
        );

        $base_url = untrailingslashit( wp_get_font_dir()['baseurl'] );
        $faces    = [];

        foreach ( $posts as $post ) {
            $settings = json_decode( $post->post_content, true );
            $file     = get_post_meta( $post->ID, '_wp_font_face_file', true );

            if ( ! is_array( $settings ) || ! is_string( $file ) || '' === $file ) {
                continue;
            }

            $faces[] = [
                'style'  => isset( $settings['fontStyle'] ) ? (string) $settings['fontStyle'] : 'normal',
                'weight' => isset( $settings['fontWeight'] ) ? (string) $settings['fontWeight'] : '400',
                'url'    => $base_url . '/' . ltrim( $file, '/' ),
            ];
        }

        return $faces;
    }

    /**
     * Installs a font into the Font Library unless the site loads it already or it is installed. Downloads only the
     * normal style at WEIGHTS. When some files fail, the font is installed with the rest; when all fail, nothing is.
     *
     * @param string $slug A LayoutDesign::FONT_FAMILIES key other than ''.
     * @return true|\WP_Error
     */
    public function ensure_installed( string $slug ) {
        if ( $this->is_provided_by_site( $slug ) || [] !== $this->faces( $slug ) ) {
            return true;
        }

        $definition = $this->collection_family( $slug );

        if ( is_wp_error( $definition ) ) {
            return $definition;
        }

        $files = $this->download_faces( $slug, $this->pick_faces( $definition['fontFace'] ) );

        if ( [] === $files ) {
            return new \WP_Error( 'font_download_failed', __( 'The font files could not be downloaded.', 'yaycheckout' ) );
        }

        $family_id = $this->family_id( $slug );

        if ( 0 === $family_id ) {
            $family_id = wp_insert_post(
                wp_slash(
                    [
                        'post_type'    => 'wp_font_family',
                        'post_status'  => 'publish',
                        'post_title'   => $definition['name'],
                        'post_name'    => sanitize_title( $slug ),
                        'post_content' => wp_json_encode( [ 'fontFamily' => $definition['fontFamily'] ] ),
                    ]
                ),
                true
            );

            if ( is_wp_error( $family_id ) ) {
                $this->delete_files( $files );

                return $family_id;
            }
        }

        foreach ( $files as $file ) {
            $settings = [
                'fontFamily' => LayoutDesign::FONT_FAMILIES[ $slug ],
                'fontStyle'  => $file['style'],
                'fontWeight' => $file['weight'],
                'src'        => $file['url'],
            ];
            // Core's font face title, which its REST controller checks for duplicates.
            $title   = \WP_Font_Utils::get_font_face_slug( $settings );
            $face_id = wp_insert_post(
                wp_slash(
                    [
                        'post_type'    => 'wp_font_face',
                        'post_parent'  => (int) $family_id,
                        'post_status'  => 'publish',
                        'post_title'   => $title,
                        'post_name'    => sanitize_title( $title ),
                        'post_content' => wp_json_encode( $settings ),
                    ]
                ),
                true
            );

            if ( is_wp_error( $face_id ) ) {
                $this->delete_files( [ $file ] );
                Logger::warning(
                    'Font face insert failed',
                    [
                        'font'  => $slug,
                        'error' => $face_id->get_error_message(),
                    ]
                );
                continue;
            }

            add_post_meta( (int) $face_id, '_wp_font_face_file', $file['relative'] );
        }

        return [] !== $this->faces( $slug )
            ? true
            : new \WP_Error( 'font_install_failed', __( 'The font could not be installed.', 'yaycheckout' ) );
    }

    /**
     * The faces ensure_installed() would upload, at Google's own URLs (fonts.gstatic.com), for the layout editor to
     * preview a font that isn't uploaded. Never for the storefront, so shoppers don't load fonts from Google.
     * [] when core's collection can't be reached.
     *
     * @param string $slug A LayoutDesign::FONT_FAMILIES key other than ''.
     * @return array<int, array{style: string, weight: string, url: string}>
     */
    public function remote_faces( string $slug ): array {
        $definition = $this->collection_family( $slug );

        return is_wp_error( $definition ) ? [] : $this->pick_faces( $definition['fontFace'] );
    }

    /**
     * A collection font's normal faces at WEIGHTS, each with its first source URL.
     *
     * @param array $faces The font's `fontFace` entries in core's collection.
     * @return array<int, array{style: string, weight: string, url: string}>
     */
    private function pick_faces( array $faces ): array {
        $picked = [];

        foreach ( $faces as $face ) {
            $style  = isset( $face['fontStyle'] ) ? (string) $face['fontStyle'] : 'normal';
            $weight = isset( $face['fontWeight'] ) ? (string) $face['fontWeight'] : '400';
            $src    = isset( $face['src'] ) ? ( is_array( $face['src'] ) ? reset( $face['src'] ) : $face['src'] ) : '';

            if ( 'normal' !== $style || ! in_array( $weight, self::WEIGHTS, true ) || ! is_string( $src ) || ! wp_http_validate_url( $src ) ) {
                continue;
            }

            $picked[] = [
                'style'  => $style,
                'weight' => $weight,
                'url'    => $src,
            ];
        }

        return $picked;
    }

    /** The Font Library family post of a font, by slug; 0 when it isn't installed. */
    private function family_id( string $slug ): int {
        $ids = get_posts(
            [
                'post_type'      => 'wp_font_family',
                'name'           => sanitize_title( $slug ),
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]
        );

        return $ids ? (int) $ids[0] : 0;
    }

    /**
     * A font's settings in core's Google Fonts collection, which core fetches once a day from WordPress.org.
     *
     * @return array{name: string, fontFamily: string, fontFace: array}|\WP_Error
     */
    private function collection_family( string $slug ) {
        if ( ! class_exists( '\WP_Font_Library' ) ) {
            return new \WP_Error( 'font_library_missing', __( 'This WordPress version has no Font Library.', 'yaycheckout' ) );
        }

        $collection = \WP_Font_Library::get_instance()->get_font_collection( self::COLLECTION );
        $data       = is_wp_error( $collection ) ? $collection : $collection->get_data();

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        foreach ( $data['font_families'] as $family ) {
            $settings = isset( $family['font_family_settings'] ) ? $family['font_family_settings'] : [];

            if ( isset( $settings['slug'] ) && $slug === $settings['slug'] && ! empty( $settings['fontFace'] ) ) {
                return [
                    'name'       => (string) $settings['name'],
                    'fontFamily' => (string) $settings['fontFamily'],
                    'fontFace'   => $settings['fontFace'],
                ];
            }
        }

        return new \WP_Error( 'font_not_in_collection', __( 'The font is not in the Google Fonts collection.', 'yaycheckout' ) );
    }

    /**
     * Downloads each face into the fonts directory.
     *
     * @param array $faces From pick_faces().
     * @return array<int, array{style: string, weight: string, url: string, file: string, relative: string}>
     */
    private function download_faces( string $slug, array $faces ): array {
        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $files = [];

        foreach ( $faces as $face ) {
            $file = $this->download( $face['url'], sprintf( '%s-%s-%s', $slug, $face['style'], $face['weight'] ) );

            if ( is_wp_error( $file ) ) {
                Logger::warning(
                    'Font download failed',
                    [
                        'font'  => $slug,
                        'src'   => $face['url'],
                        'error' => $file->get_error_message(),
                    ]
                );
                continue;
            }

            $files[] = array_merge(
                $file,
                [
                    'style'  => $face['style'],
                    'weight' => $face['weight'],
                ]
            );
        }

        return $files;
    }

    /** A font file URL's extension, lowercased, query and fragment ignored. */
    public static function extension( string $url ): string {
        return strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
    }

    /**
     * One font file, downloaded and moved into the fonts directory the way core's font face upload moves one.
     *
     * @return array{url: string, file: string, relative: string}|\WP_Error
     */
    private function download( string $src, string $name ) {
        $extension = self::extension( $src );
        $mimes     = \WP_Font_Utils::get_allowed_font_mime_types();

        if ( ! isset( $mimes[ $extension ] ) ) {
            return new \WP_Error( 'font_file_type', __( 'The font file type is not allowed.', 'yaycheckout' ) );
        }

        $tmp = download_url( $src );

        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        $mime_filter = static function () use ( $mimes ) {
            return $mimes;
        };
        add_filter( 'upload_mimes', $mime_filter );
        add_filter( 'upload_dir', '_wp_filter_font_directory' );

        // Taken by reference.
        $file  = [
            'name'     => $name . '.' . $extension,
            'tmp_name' => $tmp,
        ];
        $moved = wp_handle_sideload(
            $file,
            [
                'test_form' => false,
                'mimes'     => $mimes,
            ]
        );

        remove_filter( 'upload_dir', '_wp_filter_font_directory' );
        remove_filter( 'upload_mimes', $mime_filter );

        if ( isset( $moved['error'] ) ) {
            wp_delete_file( $tmp );

            return new \WP_Error( 'font_file_move', (string) $moved['error'] );
        }

        $base_dir = wp_normalize_path( trailingslashit( wp_get_font_dir()['basedir'] ) );
        $file     = wp_normalize_path( $moved['file'] );

        return [
            'url'      => $moved['url'],
            'file'     => $file,
            'relative' => str_starts_with( $file, $base_dir ) ? substr( $file, strlen( $base_dir ) ) : basename( $file ),
        ];
    }

    /** Removes downloaded files that no font face post holds. */
    private function delete_files( array $files ): void {
        foreach ( $files as $file ) {
            wp_delete_file( $file['file'] );
        }
    }
}
