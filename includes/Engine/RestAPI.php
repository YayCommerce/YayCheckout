<?php
namespace YayCheckout\Engine;

use YayCheckout\Controllers\SettingsRestController;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestAPI
 *
 * Handles YayCheckout REST API endpoints.
 */
class RestAPI {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'rest_api_init', [ $this, 'checkout_endpoints' ] );
    }

    public function checkout_endpoints() {
        SettingsRestController::get_instance();
    }
}
