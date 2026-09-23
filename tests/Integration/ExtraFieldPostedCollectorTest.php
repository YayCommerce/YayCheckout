<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Services\ExtraFieldPostedCollector;

/** What a posted extra field becomes on the order. Input arrives already unslashed. */
class ExtraFieldPostedCollectorTest extends WP_UnitTestCase {

    public function test_backslashes_survive(): void {
        $result = ExtraFieldPostedCollector::collect(
            [
                'note' => [
                    'fieldKey' => 'note',
                    'type'     => 'text',
                    'label'    => 'Note',
                ],
            ],
            [ 'note' => 'C:\\Users' ]
        );

        $this->assertSame( 'C:\\Users', $result['rows'][0]['value'] );
    }
}
