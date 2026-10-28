<?php
namespace YayCheckout\Engine\Admin;

use YayCheckout\Models\Layout;
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

    /**
     * The arg that opens a view other than the checkout: the logged-out view (the markup a guest who must log in
     * sees), on post.php and on a preview link (CheckoutPage::resolve()); in Pro, the thank-you and pay-order
     * pages, on post.php.
     */
    public const QUERY_VIEW = 'yc-view';

    public const VIEW_MAIN       = 'main';
    public const VIEW_LOGGED_OUT = 'logged-out';
    /** Pro's thank-you page (Layout::META_THANK_YOU). Free has no such view: views() leaves it out, so its URL opens the checkout. */
    public const VIEW_THANK_YOU = 'thank-you';
    /** Pro's pay-order page (Layout::META_PAY_ORDER). Free has no such view, as for VIEW_THANK_YOU. */
    public const VIEW_PAY_ORDER = 'pay-order';

    /** Carries QUERY_VIEW to the editor's REST calls; read by LayoutCoreRestController. */
    public const VIEW_HEADER = 'X-YayCheckout-View';

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

        $headers = [ self::REST_HEADER => '1' ];

        $view = self::current_view();

        if ( self::VIEW_MAIN !== $view ) {
            $headers[ self::VIEW_HEADER ] = $view;
        }

        wp_add_inline_script(
            'wp-api-fetch',
            sprintf(
                'wp.apiFetch.use( function ( options, next ) { return next( { ...options, headers: { ...( options.headers || {} ), ...%s } } ); } );',
                wp_json_encode( $headers )
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
     * The view asked for: the REST call's header, else the page's query arg, which covers the editor's
     * server-side preload and a preview link. Anything unknown reads as VIEW_MAIN.
     */
    public static function current_view( ?\WP_REST_Request $request = null ): string {
        if ( null !== $request && null !== $request->get_header( self::VIEW_HEADER ) ) {
            $view = sanitize_key( (string) $request->get_header( self::VIEW_HEADER ) );
        } else {
            $view = isset( $_GET[ self::QUERY_VIEW ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VIEW ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        return isset( self::views()[ $view ] ) ? $view : self::VIEW_MAIN;
    }

    /** Whether the logged-out view is asked for; see current_view(). */
    public static function is_logged_out_view( ?\WP_REST_Request $request = null ): bool {
        return self::VIEW_LOGGED_OUT === self::current_view( $request );
    }

    /** The meta that holds $view's markup, or '' for VIEW_MAIN, whose markup is post_content. */
    public static function view_meta_key( string $view ): string {
        return self::views()[ $view ] ?? '';
    }

    /**
     * Each view other than main, and the meta that holds its markup: the logged-out view, plus what Pro adds
     * (the thank-you and pay-order pages). Another page is one more entry, added through the filter, and one
     * in LayoutTemplateCatalogue::VIEW_FILES, its starter markup.
     *
     * @return array<string, string> View => meta key.
     */
    public static function views(): array {
        $core = [ self::VIEW_LOGGED_OUT => Layout::META_LOGGED_OUT ];

        /**
         * The editor views kept in layout meta, view => meta key. A view left out opens, saves and previews as the checkout.
         *
         * @param array<string, string> $views Default: the logged-out view.
         */
        $views = apply_filters( 'yaycheckout_editor_views', $core );

        return is_array( $views ) ? $views : $core;
    }

    /**
     * The embedded editor for each view of a layout, keyed like the editor's view switch. The main
     * URL is also built by editorUrl in the SPA's apps/admin/src/pages/layouts/LayoutsPage.tsx: change both together.
     *
     * @return array<string, string> main, logged-out, and thank-you and pay-order in Pro.
     */
    public static function view_urls( int $post_id ): array {
        $main = add_query_arg(
            [
                'post'           => $post_id,
                'action'         => 'edit',
                self::QUERY_FLAG => '1',
            ],
            admin_url( 'post.php' )
        );

        $urls = [ self::VIEW_MAIN => $main ];

        foreach ( array_keys( self::views() ) as $view ) {
            $urls[ $view ] = add_query_arg( self::QUERY_VIEW, $view, $main );
        }

        return $urls;
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
