<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\PostTypes\LayoutPostType;
use YayCheckout\Renderer\PreviewRequest;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: Run test's browser checks load a preview link with a signed `yaycheckout_test` argument.
 * On such a page, opened by a user who may edit layouts, this prints a catcher at the top of the
 * head that collects JavaScript errors into `window.yayCheckoutTestErrors`, for the runner in
 * packages/blocks/src/shared/layout-test-runner.ts to read. Only a Pro route can sign the argument
 * (LayoutTestRestController), so the Free build has no way to start it.
 */
class LayoutTestMode {

    use SingletonTrait;

    public const QUERY_ARG = 'yaycheckout_test';

    protected function __construct() {
        // After PreviewRequest::guard_and_attach() at 5, which settles whether the preview is valid.
        add_action( 'wp', [ $this, 'attach' ], 7 );
    }

    /** The argument's value for a preview token: ties a test run to that one link. */
    public static function sign( string $token ): string {
        return wp_hash( $token . '|layout-test' );
    }

    /** A valid preview, a user who may edit layouts, and an argument signed for this preview's token. */
    public function active(): bool {
        $preview = PreviewRequest::get_instance();

        if ( ! $preview->active() || ! current_user_can( LayoutPostType::CAPABILITY ) ) {
            return false;
        }

        $signed = isset( $_GET[ self::QUERY_ARG ] ) && is_string( $_GET[ self::QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The signature is its own MAC.

        return '' !== $signed && hash_equals( self::sign( $preview->token() ), $signed );
    }

    public function attach(): void {
        if ( ! $this->active() ) {
            return;
        }

        // wc-ajax calls and the form POST keep the argument, so the dry run's submit stays a test request.
        add_filter( 'woocommerce_ajax_get_endpoint', [ $this, 'add_arg_to_url' ], 11 );
        add_filter( 'woocommerce_get_checkout_url', [ $this, 'add_arg_to_url' ], 11 );
        add_action( 'wp_head', [ $this, 'print_error_catcher' ], 0 );
    }

    public function add_arg_to_url( string $url ): string {
        $value = self::sign( PreviewRequest::get_instance()->token() );

        // By hand, as PreviewToken::with_token() does: add_query_arg() re-encodes wc_ajax_url's %%endpoint%%.
        return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . self::QUERY_ARG . '=' . rawurlencode( $value );
    }

    /** First in the head, so errors thrown by any script after it are caught, as are scripts that fail to load. */
    public function print_error_catcher(): void {
        wp_print_inline_script_tag(
            '(function(){var e=window.yayCheckoutTestErrors=[];function a(m){if(e.length<20){e.push(String(m).slice(0,300));}}'
            . 'window.addEventListener("error",function(v){var t=v.target;if(t&&t!==window&&t.tagName==="SCRIPT"){a("Failed to load "+(t.src||"a script"));return;}'
            . 'a(v.message||"Script error")},true);'
            . 'window.addEventListener("unhandledrejection",function(v){var r=v.reason;a(r&&r.message?r.message:r)});})();'
        );
    }
}
