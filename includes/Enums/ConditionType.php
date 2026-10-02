<?php
namespace YayCheckout\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The assignment rule vocabulary: condition types, the operators each accepts and the shape of its value.
 * Constants, not a native enum: PHP 7.4 support.
 *
 * Only facts that hold for the life of a checkout form: see docs/system-architecture.md, § Storefront "Which layout".
 */
final class ConditionType {

    public const CUSTOMER_ROLE = 'customer_role';
    public const LOGIN_STATUS  = 'login_status';
    public const DEVICE        = 'device';

    /** Value is a list of role slugs. */
    public const VALUE_KEYS = 'keys';
    /** Value is one of the definition's `choices`. */
    public const VALUE_CHOICE = 'choice';

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

    /** Operators whose condition holds when its test does; not_in and is_not invert it. */
    public const AFFIRMATIVE_OPERATORS = [ self::IN, self::IS ];

    private const LIST_OPERATORS = [ self::IN, self::NOT_IN ];

    private const CHOICE_OPERATORS = [ self::IS, self::IS_NOT ];

    /**
     * @return array<string, array{operators: string[], value: string, choices?: string[]}>
     */
    public static function definitions(): array {
        return [
            self::CUSTOMER_ROLE => [
                'operators' => self::LIST_OPERATORS,
                'value'     => self::VALUE_KEYS,
            ],
            self::LOGIN_STATUS  => [
                'operators' => self::CHOICE_OPERATORS,
                'value'     => self::VALUE_CHOICE,
                'choices'   => self::LOGIN_STATUSES,
            ],
            self::DEVICE        => [
                'operators' => self::CHOICE_OPERATORS,
                'value'     => self::VALUE_CHOICE,
                'choices'   => self::DEVICES,
            ],
        ];
    }
}
