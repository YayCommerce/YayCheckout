<?php
namespace YayCheckout\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Core's wp/v2 controller for layouts, with reads gated like everything else.
 * WP_REST_Posts_Controller serves any 'publish' post to anyone before it
 * consults the post type's capabilities, so the editor-facing endpoint would
 * otherwise hand a logged-out request the layout markup.
 */
class LayoutCoreRestController extends \WP_REST_Posts_Controller {

    /**
     * @return true|\WP_Error
     */
    public function get_items_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Sorry, you are not allowed to view checkout layouts.', 'yaycheckout' ),
                [ 'status' => rest_authorization_required_code() ]
            );
        }

        return parent::get_items_permissions_check( $request );
    }

    public function check_read_permission( $post ) {
        return current_user_can( 'manage_woocommerce' ) && parent::check_read_permission( $post );
    }
}
