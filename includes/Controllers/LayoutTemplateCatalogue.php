<?php
namespace YayCheckout\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Template catalogue for the "New layout" → "From library" step in
 * YC-02b_New_Layout_Modal.dc.html.
 *
 * Currently a fixed list standing in for a real template source — does not
 * touch the database. Repoint templates()/catalogue() at real markup once it
 * exists.
 */
class LayoutTemplateCatalogue {

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
     * @param  string $slug
     * @return bool Whether $slug is one of the catalogue's templates.
     */
    public static function has_template( string $slug ): bool {
        foreach ( self::catalogue() as $template ) {
            if ( $template['slug'] === $slug ) {
                return true;
            }
        }

        return false;
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
