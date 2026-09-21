<?php
namespace YayCheckout\Services;

use YayCheckout\Helpers\ExtraFieldKeyHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Indexes a layout's block markup into fields, extra_fields, slots, and order_modes.
 *
 * Pure: a function of its input markup, with no storage reads or writes. Blocks
 * declare what they contribute through a contributor map rather than this class
 * knowing every block type. Pro adds blocks through the filter without touching
 * core.
 */
class LayoutBlockIndex {

    public const FILTER_CONTRIBUTORS = 'yaycheckout_block_contributors';

    /** Shape of the index. Keys are stable; consumers rely on them. */
    private const EMPTY = [
        'fields'       => [],
        'extra_fields' => [],
        'slots'        => [],
        'order_modes'  => [],
    ];

    /**
     * @param  string $markup Published block markup.
     * @return array
     * @throws \InvalidArgumentException When the markup is not a usable layout.
     */
    public static function from_markup( string $markup ): array {
        if ( '' === trim( $markup ) ) {
            throw new \InvalidArgumentException( 'Layout markup is empty.' );
        }

        $blocks = self::real_blocks( parse_blocks( $markup ) );

        if ( empty( $blocks ) ) {
            throw new \InvalidArgumentException( 'Layout markup contains no blocks.' );
        }

        $root = $blocks[0];

        if ( 'yaycheckout/checkout' !== $root['blockName'] ) {
            throw new \InvalidArgumentException(
                sprintf( 'Layout root must be yaycheckout/checkout, found %s.', $root['blockName'] )
            );
        }

        $index = self::EMPTY;
        ExtraFieldKeyHelper::reset();
        self::walk( $blocks, self::contributors(), $index );

        return $index;
    }

    /**
     * Block name => callable( array $block ): array returning a partial index.
     * Seeded with the blocks this plugin ships; extended via the filter.
     *
     * @return array
     */
    private static function contributors(): array {
        $map = [
            'yaycheckout/checkout'         => [ self::class, 'contribute_checkout' ],
            'yaycheckout/contact'          => [ self::class, 'contribute_contact' ],
            'yaycheckout/billing'          => [ self::class, 'contribute_billing' ],
            'yaycheckout/shipping'         => [ self::class, 'contribute_shipping' ],
            'yaycheckout/field'            => [ self::class, 'contribute_field' ],
            'yaycheckout/order-review'     => [ self::class, 'contribute_order_review' ],
            'yaycheckout/extra-field'      => [ self::class, 'contribute_extra_field' ],
            'yaycheckout/shipping-methods' => [ self::class, 'contribute_shipping_methods' ],
            'yaycheckout/express-pay'      => [ self::class, 'contribute_express_pay' ],
            'yaycheckout/payment'          => [ self::class, 'contribute_payment' ],
            'yaycheckout/order-summary'    => [ self::class, 'contribute_order_summary' ],
            'yaycheckout/place-order'      => [ self::class, 'contribute_place_order' ],
            'yaycheckout/slot'             => [ self::class, 'contribute_slot' ],
        ];

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- FILTER_CONTRIBUTORS is a yaycheckout_ prefixed constant.
        return (array) apply_filters( self::FILTER_CONTRIBUTORS, $map );
    }

    /**
     * The root block is a container: it holds sections but contributes no
     * fields, slots or order modes of its own. Present so the contributor
     * contract is visible to the blocks added in later phases and to Pro.
     *
     * @param  array $block
     * @return array
     */
    public static function contribute_checkout( array $block ): array {
        unset( $block );
        return [];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_contact( array $block ): array {
        $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
        $field = self::contribute_field(
            [
                'attrs' => [
                    'fieldKey'    => 'billing_email',
                    'type'        => 'email',
                    'label'       => isset( $attrs['label'] ) && is_string( $attrs['label'] ) ? $attrs['label'] : '',
                    'placeholder' => isset( $attrs['placeholder'] ) && is_string( $attrs['placeholder'] ) ? $attrs['placeholder'] : '',
                    'required'    => ! isset( $attrs['required'] ) || (bool) $attrs['required'],
                ],
            ]
        );

        return array_merge( [ 'slots' => [ 'contact' ] ], $field );
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_billing( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'billing' ] ];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_shipping( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'shipping' ] ];
    }

    /**
     * Empty fieldKey is skipped so a placeholder field cannot clobber the
     * index under "". Later blocks with the same key win on merge.
     *
     * @param  array $block
     * @return array
     */
    public static function contribute_field( array $block ): array {
        $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
        $key   = isset( $attrs['fieldKey'] ) ? trim( (string) $attrs['fieldKey'] ) : '';

        if ( '' === $key ) {
            return [];
        }

        $validate = [];
        if ( isset( $attrs['validate'] ) && is_array( $attrs['validate'] ) ) {
            $validate = array_values( array_map( 'strval', $attrs['validate'] ) );
        }

        $type  = isset( $attrs['type'] ) && is_string( $attrs['type'] ) && '' !== $attrs['type'] ? $attrs['type'] : 'text';
        $width = isset( $attrs['width'] ) && is_string( $attrs['width'] ) && '' !== $attrs['width'] ? $attrs['width'] : 'full';

        return [
            'fields' => [
                $key => [
                    'fieldKey'     => $key,
                    'type'         => $type,
                    'label'        => isset( $attrs['label'] ) && is_string( $attrs['label'] ) ? $attrs['label'] : '',
                    'placeholder'  => isset( $attrs['placeholder'] ) && is_string( $attrs['placeholder'] ) ? $attrs['placeholder'] : '',
                    'description'  => isset( $attrs['description'] ) && is_string( $attrs['description'] ) ? $attrs['description'] : '',
                    'required'     => ! empty( $attrs['required'] ),
                    'enabled'      => true,
                    'autocomplete' => isset( $attrs['autocomplete'] ) && is_string( $attrs['autocomplete'] ) ? $attrs['autocomplete'] : '',
                    'validate'     => $validate,
                    'width'        => $width,
                ],
            ],
        ];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_order_review( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'order_review' ] ];
    }

    /**
     * Custom merchant fields. Empty fieldKey uses the same fallback name
     * render.php posts (yaycheckout_extra_N), so persist can find the value.
     *
     * @param  array $block
     * @return array
     */
    public static function contribute_extra_field( array $block ): array {
        $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
        $key   = ExtraFieldKeyHelper::from_attributes( $attrs );

        $type    = isset( $attrs['type'] ) && is_string( $attrs['type'] ) && '' !== $attrs['type'] ? $attrs['type'] : 'text';
        $options = [];

        if ( isset( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
            foreach ( $attrs['options'] as $option ) {
                if ( ! is_array( $option ) ) {
                    continue;
                }

                $value = isset( $option['value'] ) ? (string) $option['value'] : '';
                $label = isset( $option['label'] ) ? (string) $option['label'] : $value;

                if ( '' === $value && '' === $label ) {
                    continue;
                }

                $options[] = [
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        return [
            'extra_fields' => [
                $key => [
                    'fieldKey'    => $key,
                    'type'        => $type,
                    'label'       => isset( $attrs['label'] ) && is_string( $attrs['label'] ) ? $attrs['label'] : '',
                    'placeholder' => isset( $attrs['placeholder'] ) && is_string( $attrs['placeholder'] ) ? $attrs['placeholder'] : '',
                    'required'    => ! empty( $attrs['required'] ),
                    'options'     => $options,
                ],
            ],
        ];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_shipping_methods( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'shipping_methods' ] ];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_express_pay( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'express_pay' ] ];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_payment( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'payment' ] ];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_order_summary( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'order_summary' ] ];
    }

    /**
     * @param  array $block
     * @return array
     */
    public static function contribute_place_order( array $block ): array {
        unset( $block );
        return [ 'slots' => [ 'place_order' ] ];
    }

    /**
     * Empty slotName is skipped so a placeholder cannot occupy "".
     *
     * @param  array $block
     * @return array
     */
    public static function contribute_slot( array $block ): array {
        $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
        $name  = isset( $attrs['slotName'] ) ? sanitize_key( (string) $attrs['slotName'] ) : '';

        if ( '' === $name ) {
            return [];
        }

        return [ 'slots' => [ $name ] ];
    }

    /**
     * @param array $blocks
     * @param array $contributors
     * @param array $index        Accumulated by reference.
     */
    private static function walk( array $blocks, array $contributors, array &$index ): void {
        foreach ( $blocks as $block ) {
            $name = $block['blockName'];

            if ( isset( $contributors[ $name ] ) && is_callable( $contributors[ $name ] ) ) {
                self::merge( $index, (array) call_user_func( $contributors[ $name ], $block ) );
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                self::walk( self::real_blocks( $block['innerBlocks'] ), $contributors, $index );
            }
        }
    }

    /**
     * Merge one block's contribution into the accumulated index.
     *
     * Fields and extra_fields are keyed by field name, so later blocks with the
     * same key win. Slots and order_modes are flat lists kept unique.
     *
     * @param array $index
     * @param array $contribution
     */
    private static function merge( array &$index, array $contribution ): void {
        foreach ( [ 'fields', 'extra_fields' ] as $key ) {
            if ( ! empty( $contribution[ $key ] ) ) {
                $index[ $key ] = array_merge( $index[ $key ], (array) $contribution[ $key ] );
            }
        }

        foreach ( [ 'slots', 'order_modes' ] as $key ) {
            if ( ! empty( $contribution[ $key ] ) ) {
                $index[ $key ] = array_values(
                    array_unique( array_merge( $index[ $key ], (array) $contribution[ $key ] ) )
                );
            }
        }
    }

    /**
     * Drops the entries parse_blocks() emits with a null blockName for the
     * whitespace between blocks, so callers can treat index 0 as the root.
     *
     * @param  array $blocks
     * @return array
     */
    private static function real_blocks( array $blocks ): array {
        return array_values(
            array_filter(
                $blocks,
                static function ( $block ) {
                    return ! empty( $block['blockName'] );
                }
            )
        );
    }
}
