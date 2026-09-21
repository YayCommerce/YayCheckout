<?php
namespace YayCheckout\Tests\Support;

/** An enabled classic gateway that never touches options, so its state cannot leak between tests. */
class FakeClassicGateway extends \WC_Payment_Gateway {

    public function __construct() {
        $this->id           = 'fake_classic';
        $this->method_title = 'Fake Classic';
        $this->title        = 'Pay the fake way';
        $this->enabled      = 'yes';
    }
}
