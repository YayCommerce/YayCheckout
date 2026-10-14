<?php
namespace YayCheckout\Blocks;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's icons to the Icon block's picker, as the "yaycheckout" collection.
 *
 * WordPress 7.1 opened icon registration to plugins; on older versions nothing registers and
 * has() is false, so a template falls back or leaves the icon out.
 *
 * Core keeps only <svg>, <path> and <polygon> with fill attributes, so each file in
 * assets/images/icons/ is filled outlines, not strokes.
 */
class IconRegistry {

    use SingletonTrait;

    private const COLLECTION = 'yaycheckout';

    protected function __construct() {
        add_action( 'init', [ $this, 'register_icons' ] );
    }

    public function register_icons() {
        if ( ! function_exists( 'wp_register_icon' ) ) {
            return;
        }

        wp_register_icon_collection( self::COLLECTION, [ 'label' => 'YayCheckout' ] );

        // File name in assets/images/icons/ => picker label.
        $icons = [
            'lock' => __( 'Lock', 'yaycheckout' ),
            'cart' => __( 'Cart', 'yaycheckout' ),
        ];

        foreach ( $icons as $icon => $label ) {
            wp_register_icon(
                self::COLLECTION . '/' . $icon,
                [
                    'label'     => $label,
                    'file_path' => YAYCHECKOUT_PLUGIN_DIR . 'assets/images/icons/' . $icon . '.svg',
                ]
            );
        }
    }

    /**
     * Whether the Icon block can show one of these icons, by its name without the collection.
     */
    public static function has( string $icon ): bool {
        return class_exists( 'WP_Icons_Registry' ) && \WP_Icons_Registry::get_instance()->is_registered( self::COLLECTION . '/' . $icon );
    }
}
