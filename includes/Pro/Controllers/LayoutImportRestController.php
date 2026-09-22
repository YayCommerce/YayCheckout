<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Pro\Services\LayoutImportReview;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Turns an export file into a draft in two calls: validate (review, no write) then import.
 * Pro-only: the Free build has no route, which is what makes the SPA's ProGate honest.
 */
class LayoutImportRestController extends BaseRestController {

    use SingletonTrait;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/import/validate',
            [
                [
                    // POST because the file travels in the body; nothing is stored.
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'validate_import' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->file_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/import',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'import_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->file_args(),
                ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * The review the SPA shows before anything is written.
     *
     * @return array|\WP_Error
     */
    public function validate_import( \WP_REST_Request $request ) {
        try {
            return LayoutImportReview::from_envelope( (array) $request->get_param( 'file' ) );
        } catch ( \InvalidArgumentException $e ) {
            return $this->error_invalid_arguments( $e->getMessage() );
        }
    }

    /**
     * Re-reviews and inserts the draft. The review runs again here so a client cannot import what validate refused.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function import_layout( \WP_REST_Request $request ) {
        $envelope = (array) $request->get_param( 'file' );

        try {
            LayoutImportReview::from_envelope( $envelope );
            $layout = LayoutImportReview::layout( $envelope );

            return new \WP_REST_Response( $this->layouts->import( $layout['title'], $layout['content'] )->to_array(), 201 );
        } catch ( \InvalidArgumentException $e ) {
            return $this->error_invalid_arguments( $e->getMessage() );
        }
    }

    private function file_args(): array {
        return [
            'file' => [
                'required'          => true,
                'type'              => 'object',
                'validate_callback' => static function ( $value ) {
                    return is_array( $value );
                },
            ],
        ];
    }
}
