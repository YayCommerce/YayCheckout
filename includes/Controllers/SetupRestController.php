<?php
namespace YayCheckout\Controllers;

use YayCheckout\Repositories\LayoutRepository;
use YayCheckout\Repositories\SettingsRepository;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/** POST /yaycheckout/v1/setup: the setup wizard's one save. */
class SetupRestController extends BaseRestController {
    use SingletonTrait;

    private LayoutRepository $layouts;

    private SettingsRepository $settings;

    protected function __construct() {
        $this->layouts  = new LayoutRepository();
        $this->settings = new SettingsRepository();

        register_rest_route(
            self::REST_NAMESPACE,
            '/setup',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => fn( $request ) => $this->exec_write_wp( [ $this, 'complete_setup' ], $request ),
                'permission_callback' => [ $this, 'permission_callback' ],
                'args'                => [
                    'template_slug' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => static function ( $value ) {
                            return is_string( $value ) && '' !== trim( $value );
                        },
                    ],
                    'rerun'         => [
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ],
            ]
        );
    }

    /**
     * Creates the wizard's draft layout from the picked template and marks setup done.
     *
     * The template is validated before the first write, so a refusal leaves no
     * draft or setup flag behind. Once setup is done a first run refuses
     * with 409: a retry after a lost response, or a second tab, would otherwise add
     * another draft. A re-run from Settings sends `rerun` and always adds a draft.
     *
     * Body: { template_slug, rerun? }.
     *
     * @return array|\WP_Error { layout }
     */
    public function complete_setup( \WP_REST_Request $request ) {
        if ( ! $request->get_param( 'rerun' ) && $this->settings->is_setup_done() ) {
            return $this->error_conflict( __( 'Setup is already complete.', 'yaycheckout' ) );
        }

        try {
            $template_slug = (string) $request->get_param( 'template_slug' );
            LayoutTemplateCatalogue::assert_available( $template_slug );
            $layout = $this->layouts->create( __( 'Checkout — draft', 'yaycheckout' ), $template_slug );
        } catch ( \InvalidArgumentException $ex ) {
            return $this->error_invalid_arguments( $ex->getMessage() );
        }

        $this->settings->mark_setup_done();

        return [ 'layout' => $layout->to_array() ];
    }
}
