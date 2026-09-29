<?php
namespace YayCheckout\Pro\Services;

use YayCheckout\Enums\ConditionType;
use YayCheckout\Models\Layout;
use YayCheckout\Renderer\CheckoutPage;

defined( 'ABSPATH' ) || exit;

/**
 * Picks the checkout layout by assignment rules and pins the pick to the shopper's session.
 * See docs/system-architecture.md, § Storefront "Which layout".
 */
class LayoutAssignment {

    /** WC session key: the layout id the last checkout page load served; 0 when it served WooCommerce's own. */
    private const SESSION_PINNED = 'yaycheckout_layout_id';

    /**
     * `yaycheckout_resolve_layout` callback: the checkout page load picks and pins, every other request reuses the pin.
     *
     * @param Layout|null $latest     What Free serves; rules decide here instead.
     * @param Layout[]    $candidates The active set, most recently activated first.
     */
    public static function resolve( $latest, array $candidates ): ?Layout {
        $session   = WC()->session;
        $page_load = CheckoutPage::get_instance()->on_checkout_page() && ! wp_doing_ajax() && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Compared to a literal only.

        if ( ! $page_load && null !== $session ) {
            $pinned = self::pinned( $candidates, $session->get( self::SESSION_PINNED ) );

            if ( false !== $pinned ) {
                return $pinned;
            }
        }

        $picked = self::pick( $candidates );

        if ( $page_load && null !== $session ) {
            $session->set( self::SESSION_PINNED, null !== $picked ? $picked->id : 0 );
        }

        return $picked;
    }

    /**
     * The first candidate that applies to this shopper: a layout without rules applies to everyone, so it ends the
     * search. Shopper facts are read only once a layout with rules needs them.
     *
     * @param Layout[] $candidates
     */
    public static function pick( array $candidates ): ?Layout {
        $context = null;

        foreach ( $candidates as $layout ) {
            if ( [] === $layout->rules ) {
                return $layout;
            }

            $context = $context ?? self::context();

            if ( self::matches( $layout->rules, $context ) ) {
                return $layout;
            }
        }

        return null;
    }

    /**
     * Groups are ORed, conditions inside a group ANDed.
     *
     * @param array $rules   Stored rules (Layout::sanitize_rules() shape).
     * @param array $context See context().
     */
    public static function matches( array $rules, array $context ): bool {
        foreach ( $rules as $group ) {
            if ( self::group_matches( $group, $context ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the conditions test, read once for this request.
     *
     * @return array{country: string, roles: string[], product_ids: int[], cart_total: float, device: string}
     */
    public static function context(): array {
        $customer = WC()->customer;
        $cart     = WC()->cart;

        return [
            'country'     => null !== $customer ? $customer->get_billing_country() : '',
            'roles'       => array_values( wp_get_current_user()->roles ),
            'product_ids' => null !== $cart ? self::cart_product_ids( $cart ) : [],
            'cart_total'  => null !== $cart ? self::cart_total( $cart ) : 0.0,
            // ponytail: wp_is_mobile() counts tablets as mobile; a tablet value needs its own detection.
            'device'      => wp_is_mobile() ? 'mobile' : 'desktop',
        ];
    }

    /**
     * The layout the session's pin names: a candidate, null for WooCommerce's own checkout, or false when there
     * is no usable pin (none yet, or its layout has left the active set).
     *
     * @param Layout[] $candidates
     * @param mixed    $pin
     * @return Layout|null|false
     */
    private static function pinned( array $candidates, $pin ) {
        if ( null === $pin ) {
            return false;
        }

        if ( 0 === (int) $pin ) {
            return null;
        }

        foreach ( $candidates as $layout ) {
            if ( $layout->id === (int) $pin ) {
                return $layout;
            }
        }

        return false;
    }

    private static function group_matches( array $group, array $context ): bool {
        foreach ( $group as $condition ) {
            if ( ! self::condition_matches( $condition, $context ) ) {
                return false;
            }
        }

        return true;
    }

    /** A type this version does not know never matches, whatever its operator. */
    private static function condition_matches( array $condition, array $context ): bool {
        $value = $condition['value'];

        switch ( $condition['type'] ) {
            case ConditionType::COUNTRY:
                $hit = in_array( $context['country'], $value, true );
                break;
            case ConditionType::CUSTOMER_ROLE:
                $hit = [] !== array_intersect( $context['roles'], $value );
                break;
            case ConditionType::PRODUCT_IN_CART:
                $hit = [] !== array_intersect( $context['product_ids'], $value );
                break;
            case ConditionType::DEVICE:
                $hit = $context['device'] === $value;
                break;
            case ConditionType::CART_TOTAL:
                return ConditionType::GT === $condition['operator'] ? $context['cart_total'] > $value : $context['cart_total'] < $value;
            default:
                return false;
        }

        return in_array( $condition['operator'], ConditionType::AFFIRMATIVE_OPERATORS, true ) ? $hit : ! $hit;
    }

    /**
     * Product and variation ids in the cart: a rule naming a variable product matches any of its variations.
     *
     * @return int[]
     */
    private static function cart_product_ids( \WC_Cart $cart ): array {
        $ids = [];
        foreach ( $cart->get_cart() as $item ) {
            $ids[] = (int) $item['product_id'];
            $ids[] = (int) ( $item['variation_id'] ?? 0 );
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /** Items after discounts, before shipping and fees, as the store displays prices. */
    private static function cart_total( \WC_Cart $cart ): float {
        $discount = (float) $cart->get_discount_total() + ( $cart->display_prices_including_tax() ? (float) $cart->get_discount_tax() : 0.0 );

        return (float) $cart->get_displayed_subtotal() - $discount;
    }
}
