<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Enums\ConditionType;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and replaces one layout's assignment rules. Its own route, not part of the layout save:
 * rules change who sees a layout, never what it contains. Pro-only: the Free build has no route.
 */
class LayoutRulesRestController extends BaseRestController {

    use SingletonTrait;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/rules',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'get_rules' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [ 'id' => self::id_arg() ],
                ],
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'set_rules' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [ 'id' => self::id_arg() ],
                ],
            ]
        );
    }

    public function permission_callback(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /** @return array|\WP_Error { rules, labels } */
    public function get_rules( \WP_REST_Request $request ) {
        $layout = $this->layouts->find( (int) $request->get_param( 'id' ) );

        if ( null === $layout ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        return self::reply( $layout->rules );
    }

    /**
     * Body: { rules }. Replies with the rules as stored, normalized: the caller's new baseline.
     *
     * @return array|\WP_Error { rules, labels }
     */
    public function set_rules( \WP_REST_Request $request ) {
        $id    = (int) $request->get_param( 'id' );
        $input = $request->get_json_params();

        if ( null === $this->layouts->find( $id ) ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        if ( ! is_array( $input ) || ! array_key_exists( 'rules', $input ) ) {
            return $this->error_invalid_arguments();
        }

        try {
            return self::reply( $this->layouts->set_rules( $id, $input['rules'] ) );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }
    }

    /**
     * Rules plus the names of the products they name, by id: products are too many to localize with the page,
     * so only these come back. A deleted product has no entry.
     */
    private static function reply( array $rules ): array {
        $ids = [];
        foreach ( array_merge( [], ...$rules ) as $condition ) {
            if ( ConditionType::PRODUCT_IN_CART === $condition['type'] ) {
                $ids = array_merge( $ids, $condition['value'] );
            }
        }

        $products = [];
        if ( [] !== $ids ) {
            $found = wc_get_products(
                [
                    'include' => array_unique( $ids ),
                    'limit'   => -1,
                ]
            );
            foreach ( $found as $product ) {
                $products[ $product->get_id() ] = $product->get_name();
            }
        }

        return [
            'rules'  => $rules,
            // Object even when empty: an empty PHP array would encode as a JSON list.
            'labels' => [ 'products' => (object) $products ],
        ];
    }

    private static function id_arg(): array {
        return [
            'required'          => true,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => static function ( $value ) {
                return is_numeric( $value );
            },
        ];
    }
}
