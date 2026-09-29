<?php
namespace YayCheckout\Pro\Controllers;

use YayCheckout\Controllers\BaseRestController;
use YayCheckout\Helpers\DateHelper;
use YayCheckout\Models\Layout;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/** Downloads one layout as a portable .json envelope. Pro-only: the Free build has no route, which is what makes the SPA's ProGate honest. */
class LayoutExportRestController extends BaseRestController {

    use SingletonTrait;

    private LayoutRepository $layouts;

    protected function __construct() {
        $this->layouts = new LayoutRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/layouts/(?P<id>\d+)/export',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => fn( $request ) => $this->exec_read( [ $this, 'export_layout' ], $request ),
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->id_args(),
                ],
            ]
        );
    }

    /**
     * The export envelope, with the download name in Content-Disposition so the file itself stays free of transport data.
     * `layouts` is a list even though v1 exports one, so a bundle later needs no format bump.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function export_layout( \WP_REST_Request $request ) {
        $layout = $this->layouts->find( (int) $request->get_param( 'id' ) );

        if ( null === $layout ) {
            return $this->error_not_found( __( 'Layout not found.', 'yaycheckout' ) );
        }

        $exported_at = DateHelper::now_iso8601();

        $response = new \WP_REST_Response(
            [
                'format'         => Layout::EXPORT_FORMAT,
                'plugin_version' => YAYCHECKOUT_VERSION,
                'exported_at'    => $exported_at,
                // Title, markup and assignment rules; status, active flag and dates are site state and stay behind.
                // Rules name products and roles by id: on another store those ids may point elsewhere.
                'layouts'        => [
                    [
                        'title'   => $layout->title,
                        'content' => $layout->content,
                        'rules'   => $layout->rules,
                    ],
                ],
            ]
        );
        $response->header( 'Content-Disposition', 'attachment; filename="' . self::filename( $layout, $exported_at ) . '"' );

        return $response;
    }

    /** `yaycheckout-b2b-checkout-20260922.json`: slug from the title, date from the export moment. */
    private static function filename( Layout $layout, string $exported_at ): string {
        // sanitize_title() keeps what it cannot transliterate (emoji, CJK) as %xx octets; drop them from a file name.
        $slug = trim( preg_replace( [ '/%[0-9a-f]{2}/', '/-+/' ], [ '', '-' ], sanitize_title( $layout->title ) ), '-' );

        return sprintf(
            'yaycheckout-%s-%s.json',
            '' === $slug ? 'layout' : $slug,
            substr( str_replace( '-', '', $exported_at ), 0, 8 )
        );
    }
}
