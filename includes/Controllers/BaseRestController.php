<?php
namespace YayCheckout\Controllers;

use YayCheckout\Helpers\Logger;
use YayCheckout\PostTypes\LayoutPostType;

defined( 'ABSPATH' ) || exit;

abstract class BaseRestController {

    public const REST_NAMESPACE = 'yaycheckout/v1';

    protected function error_unauthorized( $message = 'You are not allowed to perform this action.' ) {
        return new \WP_Error( 'unauthorized', $message, [ 'status' => 401 ] );
    }

    protected function error_forbidden( $message = 'You are not allowed to perform this action.' ) {
        return new \WP_Error( 'forbidden', $message, [ 'status' => 403 ] );
    }

    protected function error_not_found( $message = 'Not Found.' ) {
        return new \WP_Error( 'not_found', $message, [ 'status' => 404 ] );
    }

    protected function error_invalid_arguments( $message = 'Invalid Arguments.' ) {
        return new \WP_Error( 'invalid_arguments', $message, [ 'status' => 400 ] );
    }

    protected function error_conflict( $message = 'Conflict.' ) {
        return new \WP_Error( 'conflict', $message, [ 'status' => 409 ] );
    }

    /** Every route requires LayoutPostType::CAPABILITY. */
    public function permission_callback(): bool {
        return current_user_can( LayoutPostType::CAPABILITY );
    }

    /** Route args for an `(?P<id>\d+)` path segment. */
    protected function id_args(): array {
        return [
            'id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                // Not bare 'is_numeric': REST passes three args and an internal function throws.
                'validate_callback' => static function ( $value ) {
                    return is_numeric( $value );
                },
            ],
        ];
    }

    /**
     * Runs a route that does not write.
     *
     * @return \WP_REST_Response|\WP_Error|void
     */
    public function exec_read( $callback, \WP_REST_Request $request ) {
        try {
            if ( is_callable( $callback ) ) {
                $response = $callback( $request );
                return rest_ensure_response( $response );
            }
        } catch ( \Throwable $ex ) {
            Logger::exception( $ex, 'GET ' . $request->get_route() );

            return new \WP_Error( 'internal_error', $ex->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * Runs a route that writes through the WordPress API (posts, meta, options).
     * No SQL transaction on purpose: a ROLLBACK would leave a persistent object
     * cache holding the rolled-back write. Order writes inside the callback so a
     * partial failure leaves the old state live.
     *
     * @return \WP_REST_Response|\WP_Error|void
     */
    public function exec_write_wp( $callback, $request ) {
        try {
            if ( is_callable( $callback ) ) {
                $response = $callback( $request );
                return rest_ensure_response( $response );
            }
        } catch ( \Throwable $ex ) {
            Logger::exception( $ex, $request->get_method() . ' ' . $request->get_route() );

            return new \WP_Error( 'internal_error', $ex->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * Runs a route that writes custom tables inside a transaction, rolled back
     * on error. Custom tables only: a transaction cannot roll back the object
     * cache. No route uses it yet.
     *
     * @return \WP_REST_Response|\WP_Error|void
     */
    public function exec_write( $callback, $request ) {
        global $wpdb;

        try {
            $wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB
            if ( is_callable( $callback ) ) {
                $response = $callback( $request );
            }
            $wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB

            return rest_ensure_response( $response );

        } catch ( \Throwable $ex ) {
            $wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB

            Logger::exception( $ex, $request->get_method() . ' ' . $request->get_route(), [ 'rolled_back' => true ] );

            return new \WP_Error( 'internal_error', $ex->getMessage(), [ 'status' => 500 ] );
        }
    }
}
