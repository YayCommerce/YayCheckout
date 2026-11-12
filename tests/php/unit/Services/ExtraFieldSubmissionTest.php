<?php
namespace YayTests\Unit\Services;

use Brain\Monkey\Functions;
use YayCheckout\Services\ExtraFieldSubmission;
use YayTests\Unit\TestCase;

/**
 * @group baseline
 */
final class ExtraFieldSubmissionTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
	}

	private static function field( string $type, array $extra = [] ): array {
		return array_merge(
			[
				'label'    => 'Gift',
				'type'     => $type,
				'required' => false,
				'options'  => [
					'a' => 'Apple',
					'b' => 'Banana',
				],
			],
			$extra
		);
	}

	public function test_refuses_a_choice_that_is_not_an_option() {
		$result = ExtraFieldSubmission::collect( [ 'gift' => self::field( 'select' ) ], [ 'gift' => 'z' ] );

		$this->assertSame( [], $result['rows'] );
		$this->assertSame( [ 'gift' => 'Gift is not a valid selection.' ], $result['errors'] );

		$group = ExtraFieldSubmission::collect( [ 'gift' => self::field( 'checkbox', [ 'multiple' => true ] ) ], [ 'gift' => [ 'a', 'z' ] ] );
		$this->assertSame( [ 'gift' => 'Gift is not a valid selection.' ], $group['errors'] );
	}

	public function test_stores_a_checkbox_group_with_its_option_labels() {
		$result = ExtraFieldSubmission::collect( [ 'gift' => self::field( 'checkbox', [ 'multiple' => true ] ) ], [ 'gift' => [ 'a', '', 'b' ] ] );

		$this->assertSame( [ 'a', 'b' ], $result['rows'][0]['value'] );
		$this->assertSame( 'Apple, Banana', $result['rows'][0]['display'] );
		$this->assertSame( [], $result['errors'] );
	}

	public function test_reads_a_lone_checkbox_as_yes_or_empty() {
		$fields = [ 'gift' => self::field( 'checkbox', [ 'required' => true ] ) ];

		$ticked = ExtraFieldSubmission::collect( $fields, [ 'gift' => 'on' ] );
		$this->assertSame( '1', $ticked['rows'][0]['value'] );
		$this->assertSame( 'Yes', $ticked['rows'][0]['display'] );

		foreach ( [ '0', '', [ 'on' ] ] as $raw ) {
			$this->assertSame( [ 'gift' => 'Gift is a required field.' ], ExtraFieldSubmission::collect( $fields, [ 'gift' => $raw ] )['errors'] );
		}
	}

	public function test_accepts_only_a_datetime_that_exists_and_keeps_a_zero_text() {
		$fields = [
			'when' => self::field( 'datetime', [ 'label' => '' ] ),
			'note' => self::field( 'text' ),
		];

		$bad = ExtraFieldSubmission::collect(
            $fields,
            [
				'when' => '2026-02-30T10:00',
				'note' => '0',
            ]
        );
		$this->assertSame( [ 'when' => 'when is not a valid date and time.' ], $bad['errors'] );
		$this->assertSame( '0', $bad['rows'][0]['value'] );

		$good = ExtraFieldSubmission::collect( $fields, [ 'when' => '2026-02-10T10:00' ] );
		$this->assertSame( '2026-02-10T10:00', $good['rows'][0]['value'] );
		$this->assertSame( [], ExtraFieldSubmission::collect( $fields, [ 'when' => '2026-02-10T10:00:30' ] )['rows'] );
	}
}
