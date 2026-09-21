<?php
namespace YayCheckout\Repositories;

use YayCheckout\Helpers\DateHelper;
use YayCheckout\Models\Design;

defined( 'ABSPATH' ) || exit;

/**
 * All design storage access. Everything the Design page owns lives in one
 * option: the SPA's Save is one atomic write, and user presets travel with
 * the tokens they were saved from.
 */
class DesignRepository {

    public function get(): Design {
        return Design::from_option( get_option( Design::OPTION_DESIGN, [] ) );
    }

    /**
     * Persists the applied design and stamps updated_at; stored user presets
     * are carried over untouched. Input must already be through Design::sanitize_input().
     *
     * @param array $clean [ 'tokens', 'preset_id', 'custom_css' ].
     */
    public function save( array $clean ): Design {
        $design = $this->get();

        $design->tokens     = $clean['tokens'];
        $design->preset_id  = $clean['preset_id'];
        $design->custom_css = $clean['custom_css'];
        $design->updated_at = DateHelper::now_iso8601();

        update_option( Design::OPTION_DESIGN, $design->to_option() );

        return $design;
    }
}
