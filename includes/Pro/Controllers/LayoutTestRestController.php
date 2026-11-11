<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Helpers\DateHelper;
use YayCheckout\Helpers\PreviewToken;
use YayCheckout\Pro\Renderer\LayoutTestMode;
use YayCheckout\Renderer\PreviewRequest;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/** Run test's browser checks: a preview link signed for test mode. The route exists only in Pro. */
class LayoutTestRestController extends BaseRestController {

    use SingletonTrait;

    /** Long enough for one run; the preview link setting is for links merchants share. */
    private const LINK_MINUTES = 15;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/test-run',
            [
                [
                    // POST because a link is minted with a fresh expiry; nothing is stored.
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'create_test_run' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );
    }

    /**
     * @return array|\WP_Error { url, expires_at, refusal_message }
     */
    public function create_test_run( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_layout_not_found();
        }

        $minted = PreviewToken::mint( $id, self::LINK_MINUTES );
        $url    = PreviewToken::checkout_url( $minted['token'] );

        return [
            'url'             => $url . '&' . LayoutTestMode::QUERY_ARG . '=' . rawurlencode( LayoutTestMode::sign( $minted['token'] ) ),
            'expires_at'      => DateHelper::to_iso8601( gmdate( 'Y-m-d H:i:s', $minted['expires_at'] ) ),
            'refusal_message' => PreviewRequest::refusal_message(),
        ];
    }
}
