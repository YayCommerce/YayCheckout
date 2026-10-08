<?php
namespace YayCheckout\Engine\Admin;

use YayCheckout\Models\Layout;
use YayCheckout\Renderer\CheckoutBaseStyles;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Gives the layout editor's canvas the storefront's full width, base stylesheet and
 * Design tokens and no title field, and takes the document bar, the Publish date row
 * and core's save and status controls out of the editor around it.
 *
 * A layout is the whole checkout page, so the theme's content width, wide width
 * and root padding don't apply to it. Its name is set when it is created, so the
 * canvas shows no title to edit, and the document bar only repeats that name. The
 * manager and the editor's Activate own a layout's status, so the editor offers no
 * publish date to schedule and no status to pick.
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

    /**
     * The Publish and Status rows have no class of their own, only the dropdown inside each.
     * WordPress 6.5 has no Status row: its Visibility row and its Pending review checkbox (the
     * Summary panel's only checkbox, since layouts support neither sticky nor formats) take its place.
     * Core's save and status controls hide only once layout-actions.tsx has mounted its own
     * buttons and tagged the body, so a header it can't find still leaves core's.
     */
    private const EDITOR_CSS = '.editor-document-bar,
.editor-post-panel__row:has(.editor-post-schedule__panel-dropdown) {
	display: none;
}
body.yaycheckout-has-layout-actions :is(
	.editor-post-publish-button__button,
	.editor-post-publish-panel__toggle,
	.editor-post-save-draft,
	.editor-post-saved-state,
	.editor-post-switch-to-draft,
	.editor-post-panel__row:has(.editor-post-status),
	.editor-post-panel__row:has(.edit-post-post-visibility__toggle),
	.edit-post-post-status .editor-post-panel__row:has(.components-checkbox-control)
) {
	display: none;
}';

    protected function __construct() {
        add_filter( 'block_editor_settings_all', [ $this, 'canvas_styles' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'editor_styles' ] );
        // The hook core collects into the iframed canvas.
        add_action( 'enqueue_block_assets', [ $this, 'canvas_base_styles' ] );
    }

    /** The storefront's base stylesheet and Design tokens, without the merchant's custom CSS. */
    public function canvas_base_styles(): void {
        if ( ! $this->is_layout_editor() ) {
            return;
        }

        ( new CheckoutBaseStyles() )->enqueue( false );
    }

    private function is_layout_editor(): bool {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        return null !== $screen && $screen->is_block_editor() && Layout::POST_TYPE === $screen->post_type;
    }

    public function editor_styles(): void {
        if ( ! $this->is_layout_editor() ) {
            return;
        }

        wp_add_inline_style( 'wp-edit-post', self::EDITOR_CSS );
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
