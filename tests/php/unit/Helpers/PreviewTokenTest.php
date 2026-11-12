<?php
namespace YayTests\Unit\Helpers;

use Brain\Monkey\Functions;
use YayCheckout\Helpers\PreviewToken;
use YayTests\Unit\TestCase;

/**
 * @group baseline
 */
final class PreviewTokenTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'wp_salt' )->justReturn( 'salt-one' );
	}

	public function test_parses_the_token_it_minted() {
		$minted = PreviewToken::mint( 7, 60 );

		$this->assertSame(
			[
				'id'         => 7,
				'expires_at' => $minted['expires_at'],
			],
			PreviewToken::parse( $minted['token'] )
		);
		$this->assertEqualsWithDelta( time() + 3600, $minted['expires_at'], 2 );
	}

	public function test_refuses_a_tampered_malformed_or_expired_token() {
		$token    = PreviewToken::mint( 7, 60 )['token'];
		$last     = substr( $token, -1 );
		$tampered = substr( $token, 0, -1 ) . ( '0' === $last ? '1' : '0' );

		$this->assertNull( PreviewToken::parse( $tampered ) );
		$this->assertNull( PreviewToken::parse( 'no-dot' ) );
		$this->assertNull( PreviewToken::parse( '.mac' ) );
		$this->assertNull( PreviewToken::parse( '!!!.mac' ) );
		$this->assertNull( PreviewToken::parse( PreviewToken::mint( 7, 0 )['token'] ) );
		$this->assertNull( PreviewToken::parse( PreviewToken::mint( 0, 60 )['token'] ) );
	}

	public function test_refuses_a_token_from_another_blog_or_salt() {
		$token = PreviewToken::mint( 7, 60 )['token'];

		Functions\when( 'wp_salt' )->justReturn( 'salt-two' );
		$this->assertNull( PreviewToken::parse( $token ) );

		Functions\when( 'wp_salt' )->justReturn( 'salt-one' );
		Functions\when( 'get_current_blog_id' )->justReturn( 2 );
		$this->assertNull( PreviewToken::parse( $token ) );
	}

	public function test_appends_the_token_without_re_encoding_the_url() {
		$this->assertSame( 'https://x.test/?a=1&yaycheckout_preview=a%2Bb', PreviewToken::with_token( 'https://x.test/?a=1', 'a+b' ) );
		$this->assertSame( 'https://x.test/c/?yaycheckout_preview=t', PreviewToken::with_token( 'https://x.test/c/', 't' ) );
		$this->assertSame( '/?wc-ajax=%%endpoint%%&yaycheckout_preview=t', PreviewToken::with_token( '/?wc-ajax=%%endpoint%%', 't' ) );
	}
}
