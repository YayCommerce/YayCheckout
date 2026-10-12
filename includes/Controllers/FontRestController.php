<?php
namespace YayCheckout\Controllers;

use YayCheckout\Models\LayoutDesign;
use YayCheckout\Renderer\DesignFonts;
use YayCheckout\Repositories\FontRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/** GET /yaycheckout/v1/fonts/{slug}: a design font's faces for the layout editor canvas, and whether it is uploaded. */
class FontRestController extends BaseRestController {
    use SingletonTrait;

    protected function __construct() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/fonts/(?P<slug>[a-z0-9-]+)',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => fn( $request ) => $this->exec_read( [ $this, 'faces' ], $request ),
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
     * The `@font-face` rules the canvas prints for a font. Uploaded (or loaded by the site itself, which gives '')
     * they point at this site; not uploaded yet, they point at Google, as a preview until the layout is saved
     * (Engine\DesignFontInstall uploads it then). Uploads nothing itself.
     *
     * @return array { uploaded, css }
     */
    public function faces( \WP_REST_Request $request ): array {
        $slug     = (string) $request->get_param( 'slug' );
        $fonts    = new FontRepository();
        $uploaded = $fonts->is_provided_by_site( $slug ) || [] !== $fonts->faces( $slug );
        $design   = new DesignFonts();

        return [
            'uploaded' => $uploaded,
            'css'      => $uploaded ? $design->css( $slug ) : $design->preview_css( $slug ),
        ];
    }
}
