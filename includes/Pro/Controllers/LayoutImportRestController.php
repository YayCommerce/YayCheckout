<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Models\Layout;
use YayCheckout\Pro\Services\LayoutImportReview;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Turns an export file into a draft, or reviews one for the editor's Import.
 * Pro-only: the Free build has no route, which is what makes the SPA's ProGate honest.
 */
class LayoutImportRestController extends BaseRestController {

    use SingletonTrait;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        $file_arg = [
            'file' => [
                'required'          => true,
                'type'              => 'object',
                'validate_callback' => static function ( $value ) {
                    return is_array( $value );
                },
            ],
        ];

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/import',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'import_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $file_arg,
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/import/review',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'review_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $file_arg,
                ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * The layout an export file holds, without storing it: the editor's Import
     * replaces its own blocks, so it asks here instead of checking the file itself.
     *
     * @return array|\WP_Error { title, content, blocks }
     */
    public function review_layout( \WP_REST_Request $request ) {
        try {
            $layout = LayoutImportReview::from_envelope( (array) $request->get_param( 'file' ) );
        } catch ( \InvalidArgumentException $e ) {
            return $this->error_invalid_arguments( $e->getMessage() );
        }

        return [
            'title'   => $layout['title'],
            'content' => $layout['content'],
            'blocks'  => Layout::block_count( $layout['content'] ),
        ];
    }

    /**
     * Reviews the file, then inserts the draft. The SPA only reads the envelope, so this is
     * where a file without a Checkout block is refused; blocks the site lacks pass.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function import_layout( \WP_REST_Request $request ) {
        try {
            $layout = LayoutImportReview::from_envelope( (array) $request->get_param( 'file' ) );

            return new \WP_REST_Response( $this->layouts->import( $layout['title'], $layout['content'] )->to_array(), 201 );
        } catch ( \InvalidArgumentException $e ) {
            return $this->error_invalid_arguments( $e->getMessage() );
        }
    }
}
