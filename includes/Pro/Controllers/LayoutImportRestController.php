<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Helpers\ExceptionHelper;
use YayCheckout\Models\Layout;
use YayCheckout\Pro\Services\LayoutImportReview;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Turns an export file into a draft, or reviews one for the editor's Import.
 * The routes exist only in Pro, so Free has no import endpoint.
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

    /**
     * The layout an export file holds, without storing it, so the editor's Import checks a file the same way import does.
     * Rules are not returned; `has_rules` says whether the file holds rules an import would keep. `design` is the
     * design an import would store (LayoutDesign::to_meta()), so the editor's Import applies it with the markup.
     *
     * @return array|\WP_Error { title, content, blocks, has_rules, design }
     */
    public function review_layout( \WP_REST_Request $request ) {
        try {
            $layout = LayoutImportReview::from_envelope( (array) $request->get_param( 'file' ) );
        } catch ( \InvalidArgumentException $e ) {
            return $this->error_invalid_arguments( ExceptionHelper::message( $e ) );
        }

        return [
            'title'     => $layout['title'],
            'content'   => $layout['content'],
            'blocks'    => Layout::block_count( $layout['content'] ),
            'has_rules' => LayoutImportReview::RULES_IMPORTED === LayoutImportReview::rules_from_file( $layout['rules'] )['status'],
            'design'    => $layout['design']->to_meta(),
        ];
    }

    /**
     * Reviews the file, then inserts the draft with the file's rules, design, logged-out view and thank-you page. A file without a Checkout block is refused here;
     * blocks the site lacks pass. Unreadable rules are dropped, never refusing the file.
     *
     * @return \WP_REST_Response|\WP_Error The new draft (Layout::to_array()) plus `rules_import` { status }.
     */
    public function import_layout( \WP_REST_Request $request ) {
        try {
            $layout = LayoutImportReview::from_envelope( (array) $request->get_param( 'file' ) );
            $rules  = LayoutImportReview::rules_from_file( $layout['rules'] );
            $draft  = $this->layouts->import( $layout['title'], $layout['content'], $rules['rules'], $layout['design'], $layout['logged_out_content'], $layout['thank_you_content'] );

            return new \WP_REST_Response(
                array_merge(
                    $draft->to_array(),
                    [ 'rules_import' => [ 'status' => $rules['status'] ] ]
                ),
                201
            );
        } catch ( \InvalidArgumentException $e ) {
            return $this->error_invalid_arguments( ExceptionHelper::message( $e ) );
        }
    }
}
