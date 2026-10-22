<?php
namespace YayCheckout\Renderer;

use WP_Admin_Bar;
use YayCheckout\Engine\Admin\LayoutEditorEmbed;
use YayCheckout\Helpers\AdminUrlHelper;
use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * On a checkout or order-received page the layout renders, swaps core's Edit Page
 * and Edit site admin bar items, which edit neither the page nor the template shown,
 * for an Edit Layout item that opens the rendered layout in the admin SPA: its
 * checkout, or its thank-you page on order-received.
 */
class CheckoutAdminBar {
    use SingletonTrait;

    /** After core adds Edit site (40) and Edit Page (80), so ours takes Edit Page's place. */
    private const PRIORITY = 100;

    protected function __construct() {
        add_action( 'admin_bar_menu', [ $this, 'edit_layout_node' ], self::PRIORITY );
    }

    public function edit_layout_node( WP_Admin_Bar $admin_bar ): void {
        $checkout_page  = CheckoutPage::get_instance();
        $thank_you_page = ThankYouPage::get_instance();

        if ( $checkout_page->takes_over() ) {
            $layout = $checkout_page->layout();
            $view   = '';
        } elseif ( $thank_you_page->takes_over() ) {
            $layout = $thank_you_page->layout();
            $view   = LayoutEditorEmbed::VIEW_THANK_YOU;
        } else {
            return;
        }

        $admin_bar->remove_node( 'edit' );
        $admin_bar->remove_node( 'site-editor' );

        if ( ! current_user_can( LayoutPostType::CAPABILITY ) ) {
            return;
        }

        if ( null === $layout ) {
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
