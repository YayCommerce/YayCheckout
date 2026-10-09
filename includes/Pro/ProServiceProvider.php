<?php
namespace YayCheckout\Pro;

use YayCheckout\Models\Design;
use YayCheckout\Models\Layout;
use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Pro\Controllers\LayoutExportRestController;
use YayCheckout\Pro\Controllers\LayoutImportRestController;
use YayCheckout\Pro\Controllers\LayoutRulesRestController;
use YayCheckout\Pro\Services\AssignmentStoreData;
use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * Entry point for everything Pro; absent from the Free build. Nothing outside
 * includes/Pro/ may name a YayCheckout\Pro\* class (scripts/check-pro-boundary.sh
 * enforces it), so Pro attaches through hooks and filters only.
 */
class ProServiceProvider {

    public function boot(): void {
        // Must be first: everything downstream reads Plan::is_pro().
        Plan::mark_pro();

        add_action( 'rest_api_init', [ $this, 'rest_endpoints' ] );
        add_filter( 'yaycheckout_localize_admin_data', [ $this, 'admin_data' ] );
        add_filter( 'yaycheckout_design_for_layout', [ $this, 'design_for_layout' ], 10, 2 );
        add_action( 'init', [ $this, 'register_design_meta' ] );
        add_filter( 'rest_pre_insert_' . Layout::POST_TYPE, [ $this, 'validate_design_meta' ], 10, 2 );
        add_filter( 'update_post_metadata', [ $this, 'delete_empty_design' ], 10, 4 );
        add_filter( 'yaycheckout_editor_data', [ $this, 'editor_data' ] );
    }

    /** Adds `design` for the layout editor's Design sidebar: global tokens, the token rules and the font labels, so the bundle copies none of them. */
    public function editor_data( array $data ): array {
        $data['design'] = [
            'tokens'        => ( new DesignRepository() )->get()->tokens,
            'schema'        => Design::schema(),
            'font_families' => Design::FONT_FAMILIES,
        ];

        return $data;
    }

    /** The layout's design layer on top of global. */
    public function design_for_layout( Design $design, Layout $layout ): Design {
        return $design->with_layer( $layout->design );
    }

    /** Exposes the layer to wp/v2, so the editor saves it with the layout. */
    public function register_design_meta(): void {
        register_post_meta(
            Layout::POST_TYPE,
            Layout::META_DESIGN,
            [
                'single'            => true,
                'type'              => 'object',
                'show_in_rest'      => [
                    'schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => true,
                    ],
                ],
                'sanitize_callback' => [ Design::class, 'project_layer' ],
                'auth_callback'     => fn() => current_user_can( LayoutPostType::CAPABILITY ),
            ]
        );
    }

    /**
     * Refuses an invalid layer with a 400 before wp/v2 writes anything; the meta's
     * sanitize_callback alone would drop the bad section silently.
     *
     * @param \stdClass|\WP_Error $prepared_post
     * @return \stdClass|\WP_Error
     */
    public function validate_design_meta( $prepared_post, \WP_REST_Request $request ) {
        $meta = $request['meta'];

        // wp/v2 has already refused a non-object value against the meta's schema.
        if ( ! is_array( $meta ) || ! is_array( $meta[ Layout::META_DESIGN ] ?? null ) ) {
            return $prepared_post;
        }

        $layer = Design::validate_layer( $meta[ Layout::META_DESIGN ] );

        if ( $layer instanceof \WP_Error ) {
            return new \WP_Error( 'invalid_arguments', $layer->get_error_message(), [ 'status' => 400 ] );
        }

        return $prepared_post;
    }

    /**
     * An empty layer is deleted, never stored as {}, whoever writes it.
     *
     * @param null|bool $check
     * @param mixed     $value Already through the meta's sanitize_callback.
     * @return null|bool
     */
    public function delete_empty_design( $check, int $object_id, string $meta_key, $value ) {
        if ( Layout::META_DESIGN !== $meta_key || [] !== Design::project_layer( $value ) ) {
            return $check;
        }

        delete_post_meta( $object_id, Layout::META_DESIGN );

        return true;
    }

    /** Adds `store`: the roles the assignment picker lists. */
    public function admin_data( array $data ): array {
        $data['store'] = AssignmentStoreData::get();

        return $data;
    }

    /** Every Pro REST controller, so the Free build simply has no such route. */
    public function rest_endpoints(): void {
        LayoutExportRestController::get_instance();
        LayoutImportRestController::get_instance();
        LayoutRulesRestController::get_instance();
    }
}
