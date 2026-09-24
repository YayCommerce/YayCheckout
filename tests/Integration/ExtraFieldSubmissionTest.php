<?php
namespace YayCheckout\Tests\Integration;

use WP_UnitTestCase;
use YayCheckout\Services\ExtraFieldSubmission;
use YayCheckout\Services\LayoutMarkupReader;

/** What a posted extra field becomes on the order. Input arrives already unslashed. */
class ExtraFieldSubmissionTest extends WP_UnitTestCase {

    public function test_backslashes_survive(): void {
        $result = ExtraFieldSubmission::collect(
            self::fields(),
            [ 'note' => 'C:\\Users' ]
        );

        $this->assertSame( 'C:\\Users', $result['rows'][0]['value'] );
    }

    private const LAYOUT = '<!-- wp:yaycheckout/checkout -->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"note","label":"Note","required":true} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"when","label":"When","type":"datetime"} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"size","label":"Size","type":"select","options":[{"label":"Small","value":"s"},{"label":"Extra Large","value":""}]} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"wrap","label":"Wrap","type":"radio","required":true,"options":[{"label":"Yes","value":"yes"}]} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"addons","label":"Add-ons","type":"checkbox","options":[{"label":"Card","value":"card"},{"label":"Bow","value":"bow"}]} /-->'
        . '<!-- wp:yaycheckout/extra-field {"fieldKey":"terms","label":"Terms","type":"checkbox"} /-->'
        . '<!-- /wp:yaycheckout/checkout -->';

    private static function fields(): array {
        return LayoutMarkupReader::extra_fields( self::LAYOUT );
    }

    /**
     * Text is tag-stripped and whitespace-collapsed; a blank option value is
     * matched by its label's slug; choices display their labels; a lone
     * checkbox displays Yes; a radio value outside its options is invalid.
     */
    public function test_each_type_becomes_a_row(): void {
        $result = ExtraFieldSubmission::collect(
            self::fields(),
            [
                'note'   => '  <b>Hi</b>  there ',
                'when'   => '2026-09-24T10:00',
                'size'   => 'extra-large',
                'wrap'   => 'no',
                'addons' => [ 'card', 'bow' ],
                'terms'  => '1',
            ]
        );

        $this->assertSame(
            [
                [
                    'key'     => 'note',
                    'label'   => 'Note',
                    'type'    => 'text',
                    'value'   => 'Hi there',
                    'display' => 'Hi there',
                ],
                [
                    'key'     => 'when',
                    'label'   => 'When',
                    'type'    => 'datetime',
                    'value'   => '2026-09-24T10:00',
                    'display' => '2026-09-24T10:00',
                ],
                [
                    'key'     => 'size',
                    'label'   => 'Size',
                    'type'    => 'select',
                    'value'   => 'extra-large',
                    'display' => 'Extra Large',
                ],
                [
                    'key'     => 'addons',
                    'label'   => 'Add-ons',
                    'type'    => 'checkbox',
                    'value'   => [ 'card', 'bow' ],
                    'display' => 'Card, Bow',
                ],
                [
                    'key'     => 'terms',
                    'label'   => 'Terms',
                    'type'    => 'checkbox',
                    'value'   => '1',
                    'display' => 'Yes',
                ],
            ],
            $result['rows']
        );
        $this->assertSame(
            [ 'wrap' => 'Wrap is not a valid selection.' ],
            $result['errors']
        );
    }

    /** Required and empty is an error; one unknown choice spoils the group; an unticked box posts nothing. */
    public function test_missing_and_unknown_values(): void {
        $result = ExtraFieldSubmission::collect(
            self::fields(),
            [
                'addons' => [ 'card', 'nope' ],
                'terms'  => '0',
            ]
        );

        $this->assertSame( [], $result['rows'] );
        $this->assertSame(
            [
                'note'   => 'Note is a required field.',
                'wrap'   => 'Wrap is a required field.',
                'addons' => 'Add-ons is not a valid selection.',
            ],
            $result['errors']
        );
    }
}
