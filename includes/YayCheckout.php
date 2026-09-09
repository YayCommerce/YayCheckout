<?php
namespace YayCheckout;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * YayCheckout Plugin Initializer
 */
class YayCheckout {

    use SingletonTrait;

    protected function __construct() {
        \YayCheckout\Engine\RestAPI::get_instance();
        \YayCheckout\Engine\Register\RegisterFacade::get_instance();
        \YayCheckout\Engine\Admin\Settings::get_instance();
        \YayCheckout\Blocks\BlockRegistry::get_instance();
        \YayCheckout\PostTypes\LayoutPostType::get_instance();
    }

    public static function initialize() {
        self::get_instance();
    }

    public static function is_pro(): bool {
        return class_exists( 'YayCheckout\Pro\YayCheckoutPro', true );
    }
}
