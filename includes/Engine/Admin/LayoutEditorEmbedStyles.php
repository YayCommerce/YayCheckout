<?php
namespace YayCheckout\Engine\Admin;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Injects embed-mode CSS when post.php is loaded with ?yc-embed=1 inside the manager iframe.
 *
 * The React app opens the iframe; this class only detects the flag and hides wp-admin chrome
 * so Gutenberg fills the frame. The post is not checked to be a layout: chrome hides for any
 * post id, because the iframe is the exit path either way.
 */
class LayoutEditorEmbedStyles {

    use SingletonTrait;

    public const QUERY_FLAG = 'yc-embed';

    protected function __construct() {
        add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
        add_action( 'admin_head', [ $this, 'print_embed_styles' ] );
    }

    /**
     * @param  string $classes
     * @return string
     */
    public function admin_body_class( $classes ) {
        if ( ! $this->is_embed_request() ) {
            return $classes;
        }

        return $classes . ' yc-layout-embed';
    }

    public function print_embed_styles() {
        if ( ! $this->is_embed_request() ) {
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

            /*
             * Gutenberg positions the editor skeleton below the admin bar. Hide
             * the bar but leave that offset and you get a blank band at the top.
             */
            body.yc-layout-embed .interface-interface-skeleton {
                top: 0 !important;
            }

            body.yc-layout-embed .edit-post-layout.is-sidebar-opened .interface-interface-skeleton {
                right: 0 !important;
            }

            /* Back to post list — parent modal is the exit path. */
            body.yc-layout-embed .editor-header__back-button {
                display: none !important;
            }
        </style>
        <?php
    }

    private function is_embed_request(): bool {
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
