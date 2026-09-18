<?php
namespace YayCheckout\Engine\Admin;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Makes post.php?yc-embed=1 fit the manager's iframe: wp-admin chrome hidden,
 * and every REST call tagged so LayoutCoreRestController can withhold delete.
 * Any post id qualifies: the iframe is the exit path either way.
 */
class LayoutEditorEmbed {

    use SingletonTrait;

    public const QUERY_FLAG = 'yc-embed';

    /** Sent by the embed's apiFetch; read by LayoutCoreRestController::delete_item_permissions_check(). */
    public const REST_HEADER = 'X-YayCheckout-Embed';

    protected function __construct() {
        add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
        add_action( 'admin_head', [ $this, 'print_embed_styles' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'tag_rest_requests' ] );
    }

    /** Gutenberg asks canUser('delete') over REST, so the tag has to travel with the request, not the page. */
    public function tag_rest_requests(): void {
        if ( ! self::is_embed_request() ) {
            return;
        }

        wp_add_inline_script(
            'wp-api-fetch',
            sprintf(
                'wp.apiFetch.use( function ( options, next ) { return next( { ...options, headers: { ...( options.headers || {} ), %s: "1" } } ); } );',
                wp_json_encode( self::REST_HEADER )
            )
        );
    }

    public function admin_body_class( $classes ) {
        if ( ! self::is_embed_request() ) {
            return $classes;
        }

        return $classes . ' yc-layout-embed';
    }

    public function print_embed_styles() {
        if ( ! self::is_embed_request() ) {
            return;
        }

        ?>
        <style id="yaycheckout-layout-embed">
            html.wp-toolbar {
                padding-top: 0 !important;
            }

            body.yc-layout-embed #wpadminbar,
            body.yc-layout-embed #adminmenuback,
            body.yc-layout-embed #adminmenumain,
            body.yc-layout-embed #wpfooter {
                display: none !important;
            }

            body.yc-layout-embed #wpcontent,
            body.yc-layout-embed #wpbody-content {
                margin-left: 0 !important;
            }

            body.yc-layout-embed #wpcontent {
                padding: 0 !important;
            }

            body.yc-layout-embed #wpbody-content {
                padding-bottom: 0 !important;
            }

            /* Skeleton is offset for the admin bar; without this a blank band remains. */
            body.yc-layout-embed .interface-interface-skeleton {
                top: 0 !important;
            }

            body.yc-layout-embed .edit-post-layout.is-sidebar-opened .interface-interface-skeleton {
                right: 0 !important;
            }

            /* Back-to-list button: the parent modal is the exit path. */
            body.yc-layout-embed .editor-header__back-button {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Whether the current page request is the embedded editor. Also true while
     * the editor preloads its REST data server-side, which is why the delete
     * check consults this as well as the header.
     */
    public static function is_embed_request(): bool {
        if ( ! is_admin() || ! isset( $_GET[ self::QUERY_FLAG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        $flag = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_FLAG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( '1' !== $flag ) {
            return false;
        }

        if ( ! isset( $_GET['action'], $_GET['post'] ) || 'edit' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        $post_id = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return $post_id > 0;
    }
}
