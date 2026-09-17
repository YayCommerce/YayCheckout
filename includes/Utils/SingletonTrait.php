<?php
namespace YayCheckout\Utils;

trait SingletonTrait {
    protected static $instance = null;

    protected function __construct() { }

    public static function get_instance( ...$args ) {
        if ( ! isset( static::$instance ) ) {
            static::$instance = new static( ...$args );
        }

        return static::$instance;
    }

    protected function __clone() { }

    /**
     * @throws \Exception Always.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize a singleton.' );
    }
}
