<?php
namespace YayCheckout\Engine;

use YayCheckout\Controllers\CompatibilityRestController;
use YayCheckout\Controllers\FontRestController;
use YayCheckout\Controllers\LayoutRestController;
use YayCheckout\Controllers\SettingsRestController;
use YayCheckout\Controllers\SetupRestController;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class RestAPI {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'rest_api_init', [ $this, 'checkout_endpoints' ] );
    }

    public function checkout_endpoints() {
        SettingsRestController::get_instance();
        LayoutRestController::get_instance();
        CompatibilityRestController::get_instance();
        SetupRestController::get_instance();
        FontRestController::get_instance();
    }
}
