<?php

defined( 'ABSPATH' ) || exit;

// Set by yaycheckout.php: 'pro_active' when YayCheckout Pro supersedes this copy, 'duplicate' otherwise.
$yaycheckout_fallback_message = isset( $yaycheckout_fallback_reason ) && 'pro_active' === $yaycheckout_fallback_reason
    ? __( 'YayCheckout Pro is active, so YayCheckout has been deactivated. You can delete YayCheckout: all of the settings and data are kept.', 'yaycheckout' )
    : __( 'It looks like you have another YayCheckout version installed, please delete it before activating this new version. All of the settings and data are still preserved.', 'yaycheckout' );

add_action(
    'admin_notices',
    function () use ( $yaycheckout_fallback_message ) {
        if ( current_user_can( 'activate_plugins' ) ) {
            ?>
                <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php echo esc_html( $yaycheckout_fallback_message ); ?>
                    <a href="https://docs.yaycommerce.com/"><?php esc_html_e( 'Read more details.', 'yaycheckout' ); ?></a>
                    </strong>
                </p>
                </div>
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }
    }
);
