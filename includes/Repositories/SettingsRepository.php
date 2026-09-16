<?php
namespace YayCheckout\Repositories;

use YayCheckout\Models\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * All settings storage access. Every get_option/update_option for the
 * plugin's settings and the review flag belongs here.
 */
class SettingsRepository {

    public function get(): Settings {
        return Settings::from_option(
            get_option( Settings::OPTION_SETTINGS, [] ),
            $this->updated_at()
        );
    }

    /**
     * Persist already-sanitized values (see Settings::sanitize()) and stamp
     * the save time.
     */
    public function save( array $values ): Settings {
        update_option( Settings::OPTION_SETTINGS, $values );
        update_option( Settings::OPTION_UPDATED_AT, gmdate( 'c' ) );

        return $this->get();
    }

    public function is_reviewed(): bool {
        return (bool) get_option( Settings::OPTION_REVIEWED, false );
    }

    public function mark_reviewed(): void {
        update_option( Settings::OPTION_REVIEWED, true );
    }

    private function updated_at(): ?string {
        $updated_at = get_option( Settings::OPTION_UPDATED_AT, '' );

        return is_string( $updated_at ) && '' !== $updated_at ? $updated_at : null;
    }
}
