<?php
namespace YayCheckout\Tests\Integration\Renderer;

use WP_UnitTestCase;
use YayCheckout\Renderer\HideWhenEmpty;

/** A Group hides only when every block it watches is empty; anything else leaves it as it was. */
class HideWhenEmptyTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        // An earlier test's Group enqueued it; the unset-Group test must start clean.
        wp_dequeue_script( HideWhenEmpty::SCRIPT );
    }

    private function group( array $watch, string $inner ): string {
        $attrs = [] === $watch ? '' : ' ' . wp_json_encode( [ HideWhenEmpty::ATTRIBUTE => $watch ] );

        return do_blocks( '<!-- wp:group' . $attrs . ' --><div class="wp-block-group">' . $inner . '</div><!-- /wp:group -->' );
    }

    private function slot( string $id, string $name ): string {
        return '<!-- wp:yaycheckout/slot {"slotName":"' . $name . '","blockId":"' . $id . '"} /-->';
    }

    public function test_hides_when_every_watched_block_is_empty(): void {
        $html = $this->group( [ 'aaaa0001', 'bbbb0002' ], $this->slot( 'aaaa0001', 'one' ) . $this->slot( 'bbbb0002', 'two' ) );

        $this->assertStringContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringContainsString( 'data-yaycheckout-hide-when-empty="aaaa0001 bbbb0002"', $html );
    }

    public function test_shows_when_one_watched_block_has_content(): void {
        add_action(
            'yaycheckout_slot_two',
            static function () {
                echo '<p>Filled</p>';
            }
        );

        $html = $this->group( [ 'aaaa0001', 'bbbb0002' ], $this->slot( 'aaaa0001', 'one' ) . $this->slot( 'bbbb0002', 'two' ) );

        $this->assertStringNotContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringContainsString( 'data-yaycheckout-hide-when-empty="aaaa0001 bbbb0002"', $html );
    }

    public function test_ignores_ids_that_are_not_descendants(): void {
        $html = $this->group( [ 'zzzz9999' ], $this->slot( 'aaaa0001', 'one' ) );

        $this->assertStringNotContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringNotContainsString( 'data-yaycheckout-hide-when-empty', $html );
    }

    public function test_group_without_setting_is_unchanged(): void {
        $html = $this->group( [], $this->slot( 'aaaa0001', 'one' ) );

        $this->assertStringNotContainsString( HideWhenEmpty::HIDDEN_CLASS, $html );
        $this->assertStringNotContainsString( 'data-yaycheckout-hide-when-empty', $html );
        $this->assertFalse( wp_script_is( HideWhenEmpty::SCRIPT, 'enqueued' ) );
    }
}
