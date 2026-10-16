<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Exception messages as plain text. Every throw escapes its message with
 * esc_html(), which wordpress.org's Plugin Check requires, but a message only
 * ever leaves as JSON or a log line, where an entity would show literally
 * (`l&#039;archive`). Read a message that leaves as JSON or a log line
 * through here, not getMessage().
 */
class ExceptionHelper {

    public static function message( \Throwable $ex ): string {
        return wp_specialchars_decode( $ex->getMessage(), ENT_QUOTES );
    }
}
