<?php
namespace YayCheckout\Controllers;

use YayCheckout\Engine\Admin\LayoutEditorEmbed;

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

    /**
     * Refuses delete for the embedded editor, which reads the same answer to
     * decide whether to show Move to trash. After a trash Gutenberg
     * hard-navigates to edit.php, a screen the embed cannot host. The page
     * check covers the server-side preload; the header covers live calls.
     *
     * @return true|\WP_Error
     */
    public function delete_item_permissions_check( $request ) {
        if ( LayoutEditorEmbed::is_embed_request() || $request->get_header( LayoutEditorEmbed::REST_HEADER ) ) {
            return new \WP_Error(
                'rest_cannot_delete',
                __( 'Archive layouts from the Layouts list, not the editor.', 'yaycheckout' ),
                [ 'status' => 403 ]
            );
        }

        return parent::delete_item_permissions_check( $request );
    }
}
