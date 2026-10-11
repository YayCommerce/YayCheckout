<?php
namespace YayCheckout\Controllers;

use YayCheckout\Models\LayoutDesign;
use YayCheckout\Renderer\DesignFonts;
use YayCheckout\Repositories\FontRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/** POST /yaycheckout/v1/fonts/{slug}: installs a design font when the layout editor picks it. */
class FontRestController extends BaseRestController {
    use SingletonTrait;

    protected function __construct() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/fonts/(?P<slug>[a-z0-9-]+)',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'install' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
                'args'                => [
                    'slug' => [
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => static function ( $value ) {
                            return is_string( $value ) && '' !== $value && isset( LayoutDesign::FONT_FAMILIES[ $value ] );
                        },
                    ],
                ],
            ]
        );
    }

    /**
     * Installs the font into the Font Library unless the site loads it already, then answers with the
     * `@font-face` rules the canvas prints for it: '' when the site's own faces cover it.
     * A failed download is a 502, so the editor can say the font only shows if the theme has it.
     *
     * @return array|\WP_Error { css }
     */
    public function install( \WP_REST_Request $request ) {
        $slug      = (string) $request->get_param( 'slug' );
        $installed = ( new FontRepository() )->ensure_installed( $slug );

        if ( is_wp_error( $installed ) ) {
            return new \WP_Error( 'font_install_failed', $installed->get_error_message(), [ 'status' => 502 ] );
        }

        return [ 'css' => ( new DesignFonts() )->css( $slug ) ];
    }
}
