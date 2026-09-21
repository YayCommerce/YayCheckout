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
     * are carried over untouched. Inputs must already be validated
     * (Design::sanitize(), sanitize_custom_css(), has_preset()).
     */
    public function save( array $tokens, string $custom_css, ?string $preset_id ): Design {
        $this->write(
            [
                'tokens'     => $tokens,
                'preset_id'  => $preset_id,
                'custom_css' => $custom_css,
                'updated_at' => DateHelper::now_iso8601(),
            ],
            $this->get()
        );

        return $this->get();
    }

    /**
     * Replaces the merchant-saved preset list and nothing else — updated_at
     * describes the applied design, which this does not change. Pro-only
     * writes; core never calls it.
     *
     * @param array $presets Each { id, name, tokens, created_at }, already validated.
     */
    public function save_user_presets( array $presets ): Design {
        $this->write( [ 'presets' => $presets ], $this->get() );

        return $this->get();
    }

    private function write( array $changes, Design $current ): void {
        update_option(
            Design::OPTION_DESIGN,
            array_replace(
                [
                    'tokens'     => $current->tokens,
                    'preset_id'  => $current->preset_id,
                    'custom_css' => $current->custom_css,
                    'updated_at' => $current->updated_at,
                    'presets'    => $current->user_presets,
                ],
                $changes
            )
        );
    }
}
