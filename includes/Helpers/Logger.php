<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce → Status → Logs, source "yaycheckout". Faults only: an
 * expected 4xx (plan limit, bad markup) is not a fault and buries real ones.
 */
class Logger {

    public const SOURCE = 'yaycheckout';

    public static function error( string $message, array $context = [] ): void {
        self::log( 'error', $message, $context );
    }

    public static function warning( string $message, array $context = [] ): void {
        self::log( 'warning', $message, $context );
    }

    public static function info( string $message, array $context = [] ): void {
        self::log( 'info', $message, $context );
    }

    /** WP_DEBUG only, so calls can stay in place without filling production logs. */
    public static function debug( string $message, array $context = [] ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        self::log( 'debug', $message, $context );
    }

    /**
     * @param string $where What was being attempted, e.g. a REST route.
     */
    public static function exception( \Throwable $error, string $where, array $context = [] ): void {
        self::error(
            sprintf( '%s: %s', $where, $error->getMessage() ),
            array_merge(
                $context,
                [
                    'exception' => get_class( $error ),
                    'file'      => $error->getFile() . ':' . $error->getLine(),
                    'trace'     => $error->getTraceAsString(),
                ]
            )
        );
    }

    private static function log( string $level, string $message, array $context ): void {
        // Logging must never be what breaks a request, including early activation/upgrade runs.
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $logger = wc_get_logger();

        if ( ! $logger || ! method_exists( $logger, 'log' ) ) {
            return;
        }

        $logger->log( $level, $message, array_merge( $context, [ 'source' => self::SOURCE ] ) );
    }
}
