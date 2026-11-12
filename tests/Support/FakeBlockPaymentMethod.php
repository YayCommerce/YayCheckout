<?php
namespace YayCheckout\Tests\Support;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/** A Checkout-block payment method with no classic gateway behind it. */
class FakeBlockPaymentMethod extends AbstractPaymentMethodType {

    protected $name = 'fake_block_only';

    private bool $throws;

    public function __construct( string $name = 'fake_block_only', bool $throws = false ) {
        $this->name   = $name;
        $this->throws = $throws;
    }

    public function initialize() {}

    /** @throws \RuntimeException When built with $throws, imitating an integration that assumes a storefront request. */
    public function is_active() {
        if ( $this->throws ) {
            throw new \RuntimeException( 'needs a cart' );
        }

        return true;
    }
}
