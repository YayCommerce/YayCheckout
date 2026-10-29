<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The assignment rule vocabulary: condition types, the operators each accepts and the shape of its value.
 * Constants, not a native enum: PHP 7.4 support.
 *
 * Shopper facts hold for the life of a checkout form, so every request may read them afresh. Cart facts
 * don't: the shopper can change the cart mid-form, so they are read on the checkout page load only, and
 * the layout picked then is pinned for that form's AJAX calls and its submit (LayoutPin).
 */
final class ConditionType {

    public const CUSTOMER_ROLE = 'customer_role';
    public const LOGIN_STATUS  = 'login_status';
    public const DEVICE        = 'device';
    /** Cart items' products; a variation counts as its parent. */
    public const PRODUCT = 'product';
    /** Cart items' product categories, their ancestors included. */
    public const PRODUCT_CATEGORY = 'product_category';

    /** The types read from the cart; the context lacks CART_ITEMS until the cart loads. */
    public const CART_TYPES = [ self::PRODUCT, self::PRODUCT_CATEGORY ];

    /** Context key: list<array{product: int, categories: int[]}>, one per cart item. */
    public const CART_ITEMS = 'cart_items';

    /** Value is a list of role slugs. */
    public const VALUE_KEYS = 'keys';
    /** Value is one of the definition's `choices`. */
    public const VALUE_CHOICE = 'choice';
    /** Value is a list of post or term ids. */
    public const VALUE_IDS = 'ids';

    /** Most ids one condition may list. */
    public const MAX_IDS = 500;

    public const DESKTOP = 'desktop';
    public const MOBILE  = 'mobile';
    public const DEVICES = [ self::DESKTOP, self::MOBILE ];

    public const LOGGED_IN      = 'logged_in';
    public const GUEST          = 'guest';
    public const LOGIN_STATUSES = [ self::LOGGED_IN, self::GUEST ];

    public const IN     = 'in';
    public const NOT_IN = 'not_in';
    public const IS     = 'is';
    public const IS_NOT = 'is_not';
    /** Some cart item matches. */
    public const CONTAINS_ANY = 'contains_any';
    /** The cart has items and every one matches. */
    public const CONTAINS_ALL = 'contains_all';
    /** No cart item matches. */
    public const CONTAINS_NONE = 'contains_none';

    /** Operators whose condition holds when its test does; not_in and is_not invert it. */
    public const AFFIRMATIVE_OPERATORS = [ self::IN, self::IS ];

    private const LIST_OPERATORS = [ self::IN, self::NOT_IN ];

    private const CHOICE_OPERATORS = [ self::IS, self::IS_NOT ];

    private const CART_OPERATORS = [ self::CONTAINS_ANY, self::CONTAINS_ALL, self::CONTAINS_NONE ];

    /**
     * @return array<string, array{operators: string[], value: string, choices?: string[]}>
     */
    public static function definitions(): array {
        return [
            self::CUSTOMER_ROLE    => [
                'operators' => self::LIST_OPERATORS,
                'value'     => self::VALUE_KEYS,
            ],
            self::LOGIN_STATUS     => [
                'operators' => self::CHOICE_OPERATORS,
                'value'     => self::VALUE_CHOICE,
                'choices'   => self::LOGIN_STATUSES,
            ],
            self::DEVICE           => [
                'operators' => self::CHOICE_OPERATORS,
                'value'     => self::VALUE_CHOICE,
                'choices'   => self::DEVICES,
            ],
            self::PRODUCT          => [
                'operators' => self::CART_OPERATORS,
                'value'     => self::VALUE_IDS,
            ],
            self::PRODUCT_CATEGORY => [
                'operators' => self::CART_OPERATORS,
                'value'     => self::VALUE_IDS,
            ],
        ];
    }
}
