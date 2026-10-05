<?php
namespace YayCheckout\Controllers;

use YayCheckout\Support\Plan;

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

    /**
     * Refuses a template this plan cannot build from. Shared by every route
     * that creates from a template so the lock rule has one owner.
     *
     * @throws \InvalidArgumentException When $slug is unknown or locked on this plan.
     */
    public static function assert_available( string $slug ): void {
        $locked = array_column( self::templates( Plan::is_pro() ), 'locked', 'slug' );

        if ( ! array_key_exists( $slug, $locked ) ) {
            throw new \InvalidArgumentException( __( 'Unknown template.', 'yaycheckout' ) );
        }

        if ( $locked[ $slug ] ) {
            throw new \InvalidArgumentException( __( 'This template is part of YayCheckout Pro.', 'yaycheckout' ) );
        }
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
