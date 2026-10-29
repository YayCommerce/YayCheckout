<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Searches the products and product categories the Assignment drawer's pickers offer, and names the
 * ones a rule already lists. Searched, not sent with the page: a store may have thousands of either.
 * Pro-only: the Free build has no route.
 */
class AssignmentOptionsRestController extends BaseRestController {

    use SingletonTrait;

    /** Results per search: the picker shows a short list and the merchant narrows it by typing. */
    private const SEARCH_LIMIT = 20;

    /** Most ids one lookup names; a rule lists at most ConditionType::MAX_IDS, and the SPA pages lookups by this. */
    private const INCLUDE_LIMIT = 100;

    protected function __construct() {
        foreach ( [ 'products', 'categories' ] as $kind ) {
            register_rest_route(
                self::REST_NAMESPACE,
                '/assignment/' . $kind,
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, $kind ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->option_args(),
                ]
            );
        }
    }

    /**
     * Query: `search`, or `include` (comma-separated ids) to name picked ones. Variations are left out:
     * a rule names the parent product, which its variations match.
     *
     * @return array { options: list<array{id: int, label: string}> }
     */
    public function products( \WP_REST_Request $request ): array {
        $ids = $this->include_ids( $request );

        if ( null === $ids ) {
            $ids = \WC_Data_Store::load( 'product' )->search_products( (string) $request->get_param( 'search' ), '', false, true, self::SEARCH_LIMIT );
        }

        $options = [];

        foreach ( array_filter( array_map( 'wc_get_product', array_filter( array_map( 'intval', $ids ) ) ) ) as $product ) {
            if ( $product->is_type( 'variation' ) ) {
                continue;
            }

            $sku       = $product->get_sku();
            $options[] = [
                'id'    => $product->get_id(),
                'label' => '' === $sku ? $product->get_name() : sprintf( '%s (%s)', $product->get_name(), $sku ),
            ];
        }

        return [ 'options' => $options ];
    }

    /**
     * Query: as products(). Labels carry the ancestors ("Clothing › Shirts"): sibling categories may share a name.
     *
     * @return array { options: list<array{id: int, label: string}> }
     */
    public function categories( \WP_REST_Request $request ): array {
        $ids  = $this->include_ids( $request );
        $args = [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ];

        $args += null === $ids
            ? [
                'search' => (string) $request->get_param( 'search' ),
                'number' => self::SEARCH_LIMIT,
            ]
            : [ 'include' => $ids ];

        $terms   = get_terms( $args );
        $options = [];

        foreach ( is_array( $terms ) ? $terms : [] as $term ) {
            $path      = array_reverse( array_map( static fn( $id ) => get_term( $id )->name ?? '', get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) );
            $options[] = [
                'id'    => $term->term_id,
                'label' => implode( ' › ', array_merge( $path, [ $term->name ] ) ),
            ];
        }

        return [ 'options' => $options ];
    }

    /** @return int[]|null The `include` ids, or null for a search. */
    private function include_ids( \WP_REST_Request $request ): ?array {
        $include = (string) $request->get_param( 'include' );

        return '' === $include ? null : array_map( 'intval', explode( ',', $include ) );
    }

    private function option_args(): array {
        return [
            'search'  => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static fn( $value ) => is_string( $value ) && strlen( $value ) <= 100,
            ],
            'include' => [
                'type'              => 'string',
                'default'           => '',
                'validate_callback' => static fn( $value ) => is_string( $value )
                    && ( '' === $value || ( preg_match( '/^[1-9][0-9]{0,17}(,[1-9][0-9]{0,17})*$/', $value ) && substr_count( $value, ',' ) < self::INCLUDE_LIMIT ) ),
            ],
        ];
    }
}
