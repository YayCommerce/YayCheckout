<?php
namespace YayCheckout\Services;

use YayCheckout\Enums\ConditionType;
use YayCheckout\Models\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Picks the checkout layout by assignment rules, from shopper facts the caller reads.
 * The candidates come in the priority order the merchant sets, and overlapping rules
 * aren't checked, so the first match wins.
 */
class LayoutAssignment {

    /** For outlook(): some candidate applies whatever the missing facts turn out to be. */
    public const OUTLOOK_YES = 'yes';
    /** For outlook(): whether any candidate applies turns on the missing facts. */
    public const OUTLOOK_MAYBE = 'maybe';
    /** For outlook(): no candidate applies whatever the missing facts turn out to be. */
    public const OUTLOOK_NO = 'no';

    /**
     * The first candidate that applies to this shopper: a layout without rules applies to everyone, so it ends the search.
     * A condition on a fact $context lacks never applies.
     *
     * @param Layout[] $candidates Top priority first.
     * @param array    $context    See matches().
     */
    public static function pick( array $candidates, array $context ): ?Layout {
        foreach ( $candidates as $layout ) {
            if ( true === self::layout_state( $layout, $context ) ) {
                return $layout;
            }
        }

        return null;
    }

    /**
     * Whether pick() would return a layout, decided before every fact is known: at setup_theme the cart
     * has not loaded, yet the theme must be chosen.
     *
     * @param Layout[] $candidates As for pick().
     * @param array    $context    See matches(); the cart facts may be missing.
     * @return string An OUTLOOK_* constant.
     */
    public static function outlook( array $candidates, array $context ): string {
        $outlook = self::OUTLOOK_NO;

        foreach ( $candidates as $layout ) {
            $state = self::layout_state( $layout, $context );

            if ( true === $state ) {
                return self::OUTLOOK_YES;
            }

            if ( null === $state ) {
                $outlook = self::OUTLOOK_MAYBE;
            }
        }

        return $outlook;
    }

    /**
     * Whether any candidate's rules read the cart or the customer's countries, so the pick must wait for them and be pinned.
     *
     * @param Layout[] $candidates
     */
    public static function reads_cart( array $candidates ): bool {
        foreach ( $candidates as $layout ) {
            foreach ( $layout->rules as $group ) {
                foreach ( $group as $condition ) {
                    if ( in_array( $condition['type'], ConditionType::PINNED_TYPES, true ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Groups are ORed, conditions inside a group ANDed.
     *
     * @param array $rules   Stored rules (Layout::sanitize_rules() shape).
     * @param array $context array{customer_role: string[], login_status: string, device: string, cart_items?: array, cart_subtotal?: float, cart_quantity?: int, billing_country?: string, shipping_country?: string}, keyed by ConditionType.
     */
    public static function matches( array $rules, array $context ): bool {
        return true === self::rules_state( $rules, $context );
    }

    private static function layout_state( Layout $layout, array $context ): ?bool {
        return [] === $layout->rules ? true : self::rules_state( $layout->rules, $context );
    }

    /**
     * Three-valued: null when the answer turns on a fact $context lacks. A true group decides,
     * so an unknown one only matters when no group is true.
     */
    private static function rules_state( array $rules, array $context ): ?bool {
        $state = false;

        foreach ( $rules as $group ) {
            $group_state = self::group_state( $group, $context );

            if ( true === $group_state ) {
                return true;
            }

            if ( null === $group_state ) {
                $state = null;
            }
        }

        return $state;
    }

    /** A false condition decides, so an unknown one only matters when none is false. */
    private static function group_state( array $group, array $context ): ?bool {
        $state = true;

        foreach ( $group as $condition ) {
            $condition_state = self::condition_state( $condition, $context );

            if ( false === $condition_state ) {
                return false;
            }

            if ( null === $condition_state ) {
                $state = null;
            }
        }

        return $state;
    }

    /** A type this version does not know never matches, whatever its operator. */
    private static function condition_state( array $condition, array $context ): ?bool {
        $value = $condition['value'];

        switch ( $condition['type'] ) {
            case ConditionType::CUSTOMER_ROLE:
                $is_match = [] !== array_intersect( $context[ ConditionType::CUSTOMER_ROLE ], $value );
                break;
            case ConditionType::LOGIN_STATUS:
            case ConditionType::DEVICE:
                $is_match = $context[ $condition['type'] ] === $value;
                break;
            case ConditionType::PRODUCT:
            case ConditionType::PRODUCT_CATEGORY:
                return isset( $context[ ConditionType::CART_ITEMS ] ) ? self::cart_matches( $condition, $context[ ConditionType::CART_ITEMS ] ) : null;
            case ConditionType::BILLING_COUNTRY:
            case ConditionType::SHIPPING_COUNTRY:
                if ( ! isset( $context[ $condition['type'] ] ) ) {
                    return null;
                }

                $is_match = in_array( $context[ $condition['type'] ], $value, true );
                break;
            case ConditionType::CART_SUBTOTAL:
            case ConditionType::CART_QUANTITY:
                if ( ! isset( $context[ $condition['type'] ] ) ) {
                    return null;
                }

                return ConditionType::AT_LEAST === $condition['operator'] ? $context[ $condition['type'] ] >= $value : $context[ $condition['type'] ] < $value;
            default:
                return false;
        }

        return in_array( $condition['operator'], ConditionType::AFFIRMATIVE_OPERATORS, true ) ? $is_match : ! $is_match;
    }

    /**
     * An empty cart contains none of anything, and not only anything: "only these" must not hold for nothing.
     *
     * @param array $items Context CART_ITEMS.
     */
    private static function cart_matches( array $condition, array $items ): bool {
        $key  = ConditionType::PRODUCT === $condition['type'] ? 'product' : 'categories';
        $hits = array_filter(
            $items,
            static fn( array $item ) => [] !== array_intersect( (array) $item[ $key ], $condition['value'] )
        );

        switch ( $condition['operator'] ) {
            case ConditionType::CONTAINS_ANY:
                return [] !== $hits;
            case ConditionType::CONTAINS_ALL:
                return [] !== $items && count( $hits ) === count( $items );
            case ConditionType::CONTAINS_NONE:
                return [] === $hits;
        }

        return false;
    }
}
