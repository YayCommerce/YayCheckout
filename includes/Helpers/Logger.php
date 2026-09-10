<?php
namespace YayCheckout\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Writes to WooCommerce's log under the yaycheckout source.
 *
 * Visible at WooCommerce → Status → Logs, which is where support already
 * looks. Levels follow PSR-3, as WooCommerce's own logger does.
 *
 * What belongs here is faults — something went wrong that nobody asked for.
 * A merchant hitting a plan limit or posting bad markup is an expected 4xx,
 * not a fault, and logging those buries the real failures in noise.
 */
class Logger {

    /** WooCommerce log source. Shows as yaycheckout-{date}.log. */
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

    /**
     * Only recorded when WP_DEBUG is on, so debug calls can be left in place
     * without filling a production site's log directory.
     */
    public static function debug( string $message, array $context = [] ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        self::log( 'debug', $message, $context );
    }

    /**
     * Record a caught throwable, including where it came from.
     *
     * @param \Throwable $error
     * @param string     $where   What was being attempted, e.g. a REST route.
     * @param array      $context Extra detail to append.
     */
    public static function exception( \Throwable $error, string $where, array $context = [] ): void {
        self::error(
            sprintf( '%s: %s', $where, $error->getMessage() ),
            array_merge(
                $context,
                [
                    'exception' => get_class( $error ),
                    'file'      => $error->getFile() . ':' . $error->getLine(),

                    /*
                     * The trace is what makes a report actionable, and these
                     * logs are admin-only files on the merchant's own server.
                     */
                    'trace'     => $error->getTraceAsString(),
                ]
            )
        );
    }

    /**
     * @param string $level   PSR-3 level.
     * @param string $message
     * @param array  $context
     */
    private static function log( string $level, string $message, array $context ): void {
        /*
         * WooCommerce is a hard requirement and plugin_init() bails without
         * it, but logging must never be the thing that breaks a request —
         * including during activation or an upgrade routine that runs early.
         */
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
