<?php
namespace YayCheckout\Renderer;

use WP_Admin_Bar;
use YayCheckout\Helpers\AdminUrlHelper;
use YayCheckout\Models\Layout;
use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * On a checkout, order-received or order-pay page the layout renders, swaps core's Edit Page
 * and Edit site admin bar items, which edit neither the page nor the template shown,
 * for an Edit Layout item that opens the rendered layout in the admin SPA: its
 * checkout, or the view another page renders (the pay-order page, or Pro's thank-you page, through
 * yaycheckout_admin_bar_layout).
 */
class CheckoutAdminBar {
    use SingletonTrait;

    /** After core adds Edit site (40) and Edit Page (80), so ours takes Edit Page's place. */
    private const PRIORITY = 100;

    protected function __construct() {
        add_action( 'admin_bar_menu', [ $this, 'edit_layout_node' ], self::PRIORITY );
    }

    public function edit_layout_node( WP_Admin_Bar $admin_bar ): void {
        $checkout_page = CheckoutPage::get_instance();

        if ( $checkout_page->takes_over() ) {
            $layout = $checkout_page->layout();
            $view   = '';
        } else {
            /**
             * Another page a layout renders on this request, for the Edit Layout item: { layout: Layout, view: string },
             * view naming the editor view to open (LayoutEditorEmbed), or null. PayOrderPage answers on order-pay, and
             * Pro's thank-you page on order-received.
             *
             * @param null|array{layout: Layout, view: string} $rendered
             */
            $rendered = apply_filters( 'yaycheckout_admin_bar_layout', null );

            if ( ! is_array( $rendered ) || ! ( $rendered['layout'] ?? null ) instanceof Layout ) {
                return;
            }

            $layout = $rendered['layout'];
            $view   = is_string( $rendered['view'] ?? null ) ? $rendered['view'] : '';
        }

        $admin_bar->remove_node( 'edit' );
        $admin_bar->remove_node( 'site-editor' );

        if ( ! current_user_can( LayoutPostType::CAPABILITY ) ) {
            return;
        }

        $admin_bar->add_node(
            [
                'id'    => 'yaycheckout-edit-layout',
                'title' => '<span class="ab-icon dashicons dashicons-edit" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Edit Layout', 'yaycheckout' ) . '</span>',
                'href'  => AdminUrlHelper::edit_layout( $layout->id, $view ),
                'meta'  => [ 'title' => $layout->title ],
            ]
        );
    }
}
