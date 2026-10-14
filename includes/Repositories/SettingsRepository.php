<?php
namespace YayCheckout\Repositories;

use YayCheckout\Helpers\DateHelper;
use YayCheckout\Models\Settings;

defined( 'ABSPATH' ) || exit;

/** All settings storage access. */
class SettingsRepository {

    public function get(): Settings {
        return Settings::from_option(
            get_option( Settings::OPTION_SETTINGS, [] ),
            $this->updated_at()
        );
    }

    /** Persists values and stamps updated_at. Values must already be through Settings::sanitize(). */
    public function save( array $values ): Settings {
        update_option( Settings::OPTION_SETTINGS, $values );
        update_option( Settings::OPTION_UPDATED_AT, DateHelper::now_iso8601() );

        return $this->get();
    }

    /** Safe mode on: a layout with a problem gives way to WooCommerce's checkout (Renderer\CheckoutPage). */
    public function is_safe_mode(): bool {
        return ! empty( $this->get()->general['safe_mode'] );
    }

    public function is_reviewed(): bool {
        return (bool) get_option( Settings::OPTION_REVIEWED, false );
    }

    public function mark_reviewed(): void {
        update_option( Settings::OPTION_REVIEWED, true );
    }

    public function is_setup_done(): bool {
        return (bool) get_option( Settings::OPTION_SETUP_DONE, false );
    }

    public function mark_setup_done(): void {
        update_option( Settings::OPTION_SETUP_DONE, true );
    }

    private function updated_at(): ?string {
        $updated_at = get_option( Settings::OPTION_UPDATED_AT, '' );

        return is_string( $updated_at ) && '' !== $updated_at ? $updated_at : null;
    }
}
