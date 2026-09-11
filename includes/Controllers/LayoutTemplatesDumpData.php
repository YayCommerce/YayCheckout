<?php
namespace YayCheckout\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Dump data for the "New layout" → "From library" step in
 * YC-02b_New_Layout_Modal.dc.html. A fixed catalogue standing in for the real
 * template library, and a fixed name collision list standing in for a real
 * uniqueness check against LayoutRepository — neither touches the database.
 *
 * Delete this file and repoint LayoutRestController at the real catalogue and
 * LayoutRepository once they exist.
 */
class LayoutTemplatesDumpData {

    /**
     * Names LayoutRestController::create_draft() treats as already taken, so
     * the yc02-16 "duplicate name rejected" state is reachable without a
     * database. Matches the wireframe's own example name.
     */
    private const EXISTING_NAMES = [ 'Default checkout' ];

    /**
     * The first N templates a Free/Lite licence may pick — the rest render
     * locked, as in yc02-10.
     */
    private const FREE_UNLOCKED_COUNT = 3;

    /**
     * @param  bool $is_pro Whether the caller may use every template.
     * @return array{ slug: string, name: string, category: string, locked: bool }[]
     */
    public static function templates( bool $is_pro ): array {
        $templates = self::catalogue();

        foreach ( $templates as $index => &$template ) {
            $template['locked'] = ! $is_pro && $index >= self::FREE_UNLOCKED_COUNT;
        }

        return $templates;
    }

    /**
     * @param  string $name Name the merchant typed into the New layout modal.
     * @return string|null  Suggested unique name, or null when $name is free to use.
     */
    public static function check_duplicate_name( string $name ): ?string {
        $trimmed = trim( $name );

        foreach ( self::EXISTING_NAMES as $existing ) {
            if ( 0 === strcasecmp( $trimmed, $existing ) ) {
                return $existing . ' 2';
            }
        }

        return null;
    }

    /**
     * @return array{ slug: string, name: string, category: string }[]
     */
    private static function catalogue(): array {
        return [
            [
                'slug'     => 'minimal',
                'name'     => 'Minimal',
                'category' => 'One column',
            ],
            [
                'slug'     => 'two-column',
                'name'     => 'Two-column',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'trust',
                'name'     => 'Trust',
                'category' => 'One column',
            ],
            [
                'slug'     => 'three-step',
                'name'     => 'Three-step',
                'category' => 'Multi-step',
            ],
            [
                'slug'     => 'digital-only',
                'name'     => 'Digital only',
                'category' => 'Digital goods',
            ],
            [
                'slug'     => 'b2b-vat',
                'name'     => 'B2B / VAT',
                'category' => 'B2B',
            ],
            [
                'slug'     => 'summary-left',
                'name'     => 'Summary-left',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'express-first',
                'name'     => 'Express first',
                'category' => 'One column',
            ],
            [
                'slug'     => 'compact-pairs',
                'name'     => 'Compact pairs',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'long-form',
                'name'     => 'Long form',
                'category' => 'One column',
            ],
            [
                'slug'     => 'guest-fast-track',
                'name'     => 'Guest fast-track',
                'category' => 'One column',
            ],
            [
                'slug'     => 'split-payment',
                'name'     => 'Split payment',
                'category' => 'Multi-step',
            ],
            [
                'slug'     => 'address-first',
                'name'     => 'Address-first',
                'category' => 'One column',
            ],
            [
                'slug'     => 'coupon-forward',
                'name'     => 'Coupon-forward',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'subscription',
                'name'     => 'Subscription',
                'category' => 'Multi-step',
            ],
            [
                'slug'     => 'wholesale-bulk',
                'name'     => 'Wholesale bulk',
                'category' => 'B2B',
            ],
            [
                'slug'     => 'mobile-first',
                'name'     => 'Mobile-first',
                'category' => 'One column',
            ],
            [
                'slug'     => 'international-tax',
                'name'     => 'International tax',
                'category' => 'B2B',
            ],
        ];
    }
}
