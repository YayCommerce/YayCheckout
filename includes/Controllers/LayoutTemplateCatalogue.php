<?php
namespace YayCheckout\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Fixture list standing in for a real template source; repoint catalogue()
 * at real markup once it exists.
 */
class LayoutTemplateCatalogue {

    /** Free picks the first N; the rest render locked. */
    private const FREE_UNLOCKED_COUNT = 3;

    /**
     * @return array{ slug: string, name: string, category: string, locked: bool }[]
     */
    public static function templates( bool $is_pro ): array {
        $templates = self::catalogue();

        foreach ( $templates as $index => &$template ) {
            $template['locked'] = ! $is_pro && $index >= self::FREE_UNLOCKED_COUNT;
        }

        return $templates;
    }

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
                'slug'     => 'classic-two-column',
                'name'     => 'Classic two-column',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'split-express',
                'name'     => 'Split express',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'editorial-fashion',
                'name'     => 'Editorial fashion',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'multi-step-wizard',
                'name'     => 'Multi-step wizard',
                'category' => 'Multi-step',
            ],
            [
                'slug'     => 'accordion-one-page',
                'name'     => 'Accordion one-page',
                'category' => 'One column',
            ],
            [
                'slug'     => 'upsell-cross-sell',
                'name'     => 'Upsell & cross-sell',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'minimal-steps',
                'name'     => 'Minimal steps',
                'category' => 'Multi-step',
            ],
            [
                'slug'     => 'live-receipt',
                'name'     => 'Live receipt',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'soft-steps',
                'name'     => 'Soft steps',
                'category' => 'Multi-step',
            ],
            [
                'slug'     => 'material-stepper',
                'name'     => 'Material stepper',
                'category' => 'Two column',
            ],
            [
                'slug'     => 'digital-product',
                'name'     => 'Digital product',
                'category' => 'Digital goods',
            ],
        ];
    }
}
