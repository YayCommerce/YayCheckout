<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Helpers\DateHelper;
use YayCheckout\Helpers\ExceptionHelper;
use YayCheckout\Helpers\PreviewToken;
use YayCheckout\Pro\Repositories\PreviewOrderRepository;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * A layout's pay-order page: adding one to a layout that has none, and its preview link, which opens the
 * sample order's order-pay page. Pro-only: the Free build has no route.
 */
class PayOrderRestController extends BaseRestController {

    use SingletonTrait;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/pay-order',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'add_pay_order' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/pay-order/preview-link',
            [
                [
                    // POST because a link is minted with a fresh expiry; exec_write_wp because the first preview makes the sample order.
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'create_preview_link' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );
    }

    /**
     * Gives a layout its pay-order page from the layout editor's Add pay-order page button.
     *
     * @return array|\WP_Error The layout (Layout::to_array()).
     */
    public function add_pay_order( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_layout_not_found();
        }

        try {
            return $this->layouts->add_pay_order( $id )->to_array();
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( ExceptionHelper::message( $ex ) );
        }
    }

    /**
     * Mints an expiring preview URL, as core's /layouts/{id}/preview-link does, to the sample order's
     * order-pay page instead of the checkout.
     *
     * @return array|\WP_Error { url, expires_at }
     */
    public function create_preview_link( \WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_layout_not_found();
        }

        $lifetime = (int) ( new SettingsRepository() )->get()->advanced['preview_link_lifetime'];
        $minted   = PreviewToken::mint( $id, $lifetime );

        return [
            'url'        => PreviewToken::with_token( ( new PreviewOrderRepository() )->get_or_create()->get_checkout_payment_url(), $minted['token'] ),
            'expires_at' => DateHelper::to_iso8601( gmdate( 'Y-m-d H:i:s', $minted['expires_at'] ) ),
        ];
    }
}
