<?php
namespace YayCheckout\Engine;

use YayCheckout\Helpers\Logger;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Repositories\FontRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Uploads a layout's design font into the Font Library when its design is saved, unless the site loads it
 * already or it is uploaded. Every design writer saves through the post meta: the editor's Save (core's wp/v2
 * REST), and LayoutRepository creating a layout from a template, an import or a duplicate, or resetting one.
 * A meta write that changes nothing fires no meta hook, so the editor's Save is also caught after the fact:
 * a font deleted from the Font Library uploads again on the next Save, design changed or not.
 * A failed upload never fails the save: the checkout falls back to a system font, and the editor says so.
 */
class DesignFontInstall {

    use SingletonTrait;

    /**
     * Fonts already tried in this request, since an editor Save that changes the design fires both hooks.
     *
     * @var array<string, bool>
     */
    private array $tried = [];

    protected function __construct() {
        add_action( 'added_post_meta', [ $this, 'install_font' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'install_font' ], 10, 4 );
        add_action( 'rest_after_insert_' . Layout::POST_TYPE, [ $this, 'install_saved_font' ] );
    }

    /** @param \WP_Post $post The layout the editor saved. */
    public function install_saved_font( $post ): void {
        $this->install( (int) $post->ID, get_post_meta( $post->ID, Layout::META_DESIGN, true ) );
    }

    /**
     * @param int    $meta_id    Unused.
     * @param int    $post_id    The post whose meta was written.
     * @param string $meta_key   The meta key written.
     * @param mixed  $meta_value The meta value written, unslashed.
     */
    public function install_font( $meta_id, $post_id, $meta_key, $meta_value ): void {
        if ( Layout::META_DESIGN !== $meta_key || Layout::POST_TYPE !== get_post_type( $post_id ) ) {
            return;
        }

        $this->install( (int) $post_id, $meta_value );
    }

    /** @param mixed $design The layout's stored design. */
    private function install( int $post_id, $design ): void {
        $slug = (string) LayoutDesign::from_meta( $design )->sections['typography']['font_family'];

        if ( '' === $slug || isset( $this->tried[ $slug ] ) ) {
            return;
        }

        $this->tried[ $slug ] = true;
        $installed            = ( new FontRepository() )->ensure_installed( $slug );

        if ( is_wp_error( $installed ) ) {
            Logger::warning(
                'Design font upload failed',
                [
                    'layout' => $post_id,
                    'font'   => $slug,
                    'error'  => $installed->get_error_message(),
                ]
            );
        }
    }
}
