<?php
namespace YayCheckout\Services;

use YayCheckout\Enums\ConditionType;
use YayCheckout\Models\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Picks the checkout layout by assignment rules, from shopper facts the caller reads.
 * The candidates' order stands in for a priority the merchant can't set, and overlapping
 * rules aren't checked, so the first match wins.
 */
class LayoutAssignment {

    /**
     * The first candidate that applies to this shopper: a layout without rules applies to everyone, so it ends the search.
     *
     * @param Layout[] $candidates Most recently activated first.
     * @param array    $context    See matches().
     */
    public static function pick( array $candidates, array $context ): ?Layout {
        foreach ( $candidates as $layout ) {
            if ( [] === $layout->rules || self::matches( $layout->rules, $context ) ) {
                return $layout;
            }
        }

        return null;
    }

    /**
     * Groups are ORed, conditions inside a group ANDed.
     *
     * @param array $rules   Stored rules (Layout::sanitize_rules() shape).
     * @param array $context array{customer_role: string[], login_status: string, device: string}, keyed by ConditionType.
     */
    public static function matches( array $rules, array $context ): bool {
        foreach ( $rules as $group ) {
            if ( self::group_matches( $group, $context ) ) {
                return true;
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
            case ConditionType::CUSTOMER_ROLE:
                $is_match = [] !== array_intersect( $context[ ConditionType::CUSTOMER_ROLE ], $value );
                break;
            case ConditionType::LOGIN_STATUS:
            case ConditionType::DEVICE:
                $is_match = $context[ $condition['type'] ] === $value;
                break;
            default:
                return false;
        }

        return in_array( $condition['operator'], ConditionType::AFFIRMATIVE_OPERATORS, true ) ? $is_match : ! $is_match;
    }
}
