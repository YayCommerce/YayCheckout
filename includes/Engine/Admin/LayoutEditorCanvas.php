<?php
namespace YayCheckout\Engine\Admin;

use YayCheckout\Models\Layout;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Gives the layout editor's canvas the storefront's full width and no title field.
 *
 * A layout is the whole checkout page, so the theme's content width, wide width
 * and root padding don't apply to it. Its name is set when it is created, so the
 * canvas shows no title to edit.
 */
class LayoutEditorCanvas {

    use SingletonTrait;

    /**
     * The root container's own rules print the theme's sizes as literal pixels, not the
     * custom properties, so they are overridden by name. The iframed canvas doesn't prefix
     * editor styles and prints core's rules later, so the repeated class wins on specificity.
     * The edit-post class is the title wrapper's name before WordPress 6.5.
     */
    private const CANVAS_CSS = '.is-root-container {
	--wp--style--global--content-size: 100%;
	--wp--style--global--wide-size: 100%;
	--wp--style--root--padding-left: 0px;
	--wp--style--root--padding-right: 0px;
	padding-left: 0;
	padding-right: 0;
}
.block-editor-block-list__layout.is-root-container.is-root-container > :where(:not(.alignleft):not(.alignright):not(.alignfull)),
.block-editor-block-list__layout.is-root-container.is-root-container > .alignwide {
	max-width: none;
}
.editor-visual-editor__post-title-wrapper,
.edit-post-visual-editor__post-title-wrapper {
	display: none;
}';

    protected function __construct() {
        add_filter( 'block_editor_settings_all', [ $this, 'canvas_styles' ], 10, 2 );
    }

    /**
     * No __unstableType: a theme style is dropped when the "Use theme styles" preference is off.
     *
     * @param array                    $settings
     * @param \WP_Block_Editor_Context $context
     * @return array
     */
    public function canvas_styles( array $settings, $context ): array {
        if ( ! $context->post instanceof \WP_Post || Layout::POST_TYPE !== $context->post->post_type ) {
            return $settings;
        }

        $settings['styles']   = isset( $settings['styles'] ) ? $settings['styles'] : [];
        $settings['styles'][] = [ 'css' => self::CANVAS_CSS ];

        return $settings;
    }
}
