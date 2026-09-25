<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The assignment rule vocabulary: condition types, the operators each accepts and the shape of its value.
 * The SPA's condition-types.ts mirrors it. Constants, not a native enum: PHP 7.4 support.
 */
final class ConditionType {

    public const COUNTRY         = 'country';
    public const PRODUCT_IN_CART = 'product_in_cart';
    public const CUSTOMER_ROLE   = 'customer_role';
    public const DEVICE          = 'device';
    public const PAYMENT_METHOD  = 'payment_method';
    public const CART_TOTAL      = 'cart_total';

    /** Value is a list of string keys (country codes, role slugs, gateway ids). */
    public const VALUE_KEYS = 'keys';
    /** Value is a list of post ids. */
    public const VALUE_IDS = 'ids';
    /** Value is one of self::DEVICES. */
    public const VALUE_DEVICE = 'device';
    /** Value is a non-negative amount in the store currency. */
    public const VALUE_AMOUNT = 'amount';

    public const DEVICES = [ 'all', 'desktop', 'mobile' ];

    private const LIST_OPERATORS = [ 'in', 'not_in' ];

    /**
     * @return array<string, array{operators: string[], value: string}>
     */
    public static function definitions(): array {
        return [
            self::COUNTRY         => [
                'operators' => self::LIST_OPERATORS,
                'value'     => self::VALUE_KEYS,
            ],
            self::PRODUCT_IN_CART => [
                'operators' => self::LIST_OPERATORS,
                'value'     => self::VALUE_IDS,
            ],
            self::CUSTOMER_ROLE   => [
                'operators' => self::LIST_OPERATORS,
                'value'     => self::VALUE_KEYS,
            ],
            self::DEVICE          => [
                'operators' => [ 'is', 'is_not' ],
                'value'     => self::VALUE_DEVICE,
            ],
            self::PAYMENT_METHOD  => [
                'operators' => self::LIST_OPERATORS,
                'value'     => self::VALUE_KEYS,
            ],
            self::CART_TOTAL      => [
                'operators' => [ 'gt', 'lt' ],
                'value'     => self::VALUE_AMOUNT,
            ],
        ];
    }
}
