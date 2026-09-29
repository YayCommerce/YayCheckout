<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The assignment rule vocabulary: condition types, the operators each accepts and the shape of its value.
 * Constants, not a native enum: PHP 7.4 support.
 */
final class ConditionType {

    public const COUNTRY         = 'country';
    public const PRODUCT_IN_CART = 'product_in_cart';
    public const CUSTOMER_ROLE   = 'customer_role';
    public const DEVICE          = 'device';
    public const CART_TOTAL      = 'cart_total';

    /** Value is a list of string keys (country codes, role slugs). */
    public const VALUE_KEYS = 'keys';
    /** Value is a list of post ids. */
    public const VALUE_IDS = 'ids';
    /** Value is one of self::DEVICES. */
    public const VALUE_DEVICE = 'device';
    /** Value is a non-negative amount in the store currency. */
    public const VALUE_AMOUNT = 'amount';

    public const DEVICES = [ 'desktop', 'mobile' ];

    public const IN     = 'in';
    public const NOT_IN = 'not_in';
    public const IS     = 'is';
    public const IS_NOT = 'is_not';
    public const GT     = 'gt';
    public const LT     = 'lt';

    /** Operators whose condition holds when its test does; not_in and is_not invert it. */
    public const AFFIRMATIVE_OPERATORS = [ self::IN, self::IS ];

    private const LIST_OPERATORS = [ self::IN, self::NOT_IN ];

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
                'operators' => [ self::IS, self::IS_NOT ],
                'value'     => self::VALUE_DEVICE,
            ],
            self::CART_TOTAL      => [
                'operators' => [ self::GT, self::LT ],
                'value'     => self::VALUE_AMOUNT,
            ],
        ];
    }
}
