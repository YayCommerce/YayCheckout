<?php
namespace YayCheckout\Controllers;

use YayCheckout\Repositories\DesignRepository;
use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * POST /yaycheckout/v1/setup: the setup wizard's one save.
 */
class SetupRestController extends BaseRestController {
    use SingletonTrait;

    private LayoutRepository $layouts;

    private DesignRepository $design;

    private SettingsRepository $settings;

    protected function __construct() {
        $this->layouts  = new LayoutRepository();
        $this->design   = new DesignRepository();
        $this->settings = new SettingsRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/setup',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'complete_setup' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
            ]
        );
    }

    /**
     * Creates the wizard's draft layout, saves its design and marks setup done.
     *
     * Everything is validated before the first write, so a refusal leaves no
     * draft, design or setup flag behind; the steps after the draft cannot fail
     * validation.
     *
     * Body: { template_slug, design: { tokens, preset_id, custom_css } }.
     *
     * @return array|\WP_Error { layout, design }
     */
    public function complete_setup( \WP_REST_Request $request ) {
        $input  = $request->get_json_params();
        $design = is_array( $input ) ? ( $input['design'] ?? null ) : null;

        if ( ! is_array( $design ) ) {
            return $this->error_invalid_arguments();
        }

        if ( ! LayoutTemplateCatalogue::has_template( (string) ( $input['template_slug'] ?? '' ) ) ) {
            return $this->error_invalid_arguments( __( 'Unknown template.', 'yaycheckout' ) );
        }

        $clean = $this->design->get()->sanitize_input( $design );

        if ( $clean instanceof \WP_Error ) {
            return $this->error_invalid_arguments( $clean->get_error_message() );
        }

        try {
            $layout = $this->layouts->create( __( 'Checkout — draft', 'yaycheckout' ) );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }

        $saved = $this->design->save( $design );
        $this->settings->mark_setup_done();

        return [
            'layout' => $layout->to_array(),
            'design' => $saved->to_array(),
        ];
    }
}
