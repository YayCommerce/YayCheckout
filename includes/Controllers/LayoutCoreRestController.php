<?php
namespace YayCheckout\Controllers;

use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Engine\Admin\LayoutEditorEmbed;
use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * Core's wp/v2 controller for layouts, with reads gated like everything else and
 * publishing held to the same layout rules. WP_REST_Posts_Controller serves any
 * 'publish' post to anyone before it consults the post type's capabilities, so
 * a logged-out request would otherwise get the layout markup.
 */
class LayoutCoreRestController extends \WP_REST_Posts_Controller {

    /** @return true|\WP_Error */
    public function get_items_permissions_check( $request ) {
        if ( ! current_user_can( LayoutPostType::CAPABILITY ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Sorry, you are not allowed to view checkout layouts.', 'yaycheckout' ),
                [ 'status' => rest_authorization_required_code() ]
            );
        }

        return parent::get_items_permissions_check( $request );
    }

    public function check_read_permission( $post ) {
        return current_user_can( LayoutPostType::CAPABILITY ) && parent::check_read_permission( $post );
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

    /**
     * Refuses a save that would leave the layout published or scheduled while
     * LayoutMarkupReader::publish_error() objects, so the editor's Publish
     * button follows the same rules as LayoutRepository::publish(). Drafts
     * still save.
     *
     * @return \stdClass|\WP_Error
     */
    protected function prepare_item_for_database( $request ) {
        $prepared = parent::prepare_item_for_database( $request );

        if ( is_wp_error( $prepared ) ) {
            return $prepared;
        }

        // The logged-out view saves into its meta; post_content, which the checks below read, stays as stored.
        if ( LayoutEditorEmbed::is_logged_out_view( $request ) && isset( $prepared->post_content ) ) {
            // Unslashed here; update_item() slashes later.
            $prepared->meta_input = [
                Layout::META_LOGGED_OUT => LayoutRepository::filter_logged_out_content( $prepared->post_content ),
            ];
            unset( $prepared->post_content );
        }

        $stored  = isset( $prepared->ID ) ? get_post( $prepared->ID ) : null;
        $status  = $prepared->post_status ?? ( $stored ? $stored->post_status : '' );
        $content = $prepared->post_content ?? ( $stored ? $stored->post_content : '' );

        if ( ! in_array( $status, LayoutStatus::live(), true ) ) {
            return $prepared;
        }

        // Neither the markup nor the status changes, as when the logged-out view saves: the stored markup went
        // live under these rules, and a rule it fails since (Pro blocks after a downgrade) must not block the save.
        if ( ! isset( $prepared->post_content ) && null !== $stored && $stored->post_status === $status ) {
            return $prepared;
        }

        $error = LayoutMarkupReader::publish_error( $content, BlockRegistry::locked_blocks() );

        if ( '' !== $error ) {
            return new \WP_Error( 'yaycheckout_layout_unpublishable', $error, [ 'status' => 400 ] );
        }

        return $prepared;
    }

    /** In the logged-out view the editor loads that view's markup as the post content, or its template's when none is saved. */
    public function prepare_item_for_response( $item, $request ) {
        if ( ! LayoutEditorEmbed::is_logged_out_view( $request ) || 'edit' !== $request['context'] ) {
            return parent::prepare_item_for_response( $item, $request );
        }

        // Skips rendering the main markup into content.rendered, which is replaced below.
        add_filter( 'the_content', '__return_empty_string', PHP_INT_MIN );
        $response = parent::prepare_item_for_response( $item, $request );
        remove_filter( 'the_content', '__return_empty_string', PHP_INT_MIN );

        $data   = $response->get_data();
        $markup = (string) get_post_meta( $item->ID, Layout::META_LOGGED_OUT, true );

        if ( isset( $data['content'] ) ) {
            $data['content']['raw']      = '' !== $markup ? $markup : LayoutTemplateCatalogue::logged_out_markup( (string) get_post_meta( $item->ID, Layout::META_TEMPLATE, true ) );
            $data['content']['rendered'] = '';
            $response->set_data( $data );
        }

        return $response;
    }
}
