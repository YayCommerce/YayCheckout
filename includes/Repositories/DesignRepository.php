<?php
namespace YayCheckout\Repositories;

use YayCheckout\Helpers\DateHelper;
use YayCheckout\Models\Design;

defined( 'ABSPATH' ) || exit;

/**
 * All design storage access. Everything the Design page owns lives in one
 * option, so the SPA's Save is one atomic write.
 */
class DesignRepository {

    public function get(): Design {
        return Design::from_option( get_option( Design::OPTION_DESIGN, [] ) );
    }

    /**
     * Validates and persists the applied design, stamping updated_at.
     *
     * @param array $input Raw { tokens, custom_css } body.
     * @throws \InvalidArgumentException When the body fails Design::sanitize_input().
     */
    public function save( array $input ): Design {
        return $this->store( $this->prepare( $input ) );
    }

    /**
     * The stored design with $input applied, not yet persisted.
     *
     * @param array $input Raw { tokens, custom_css } body.
     * @throws \InvalidArgumentException When the body fails Design::sanitize_input().
     */
    private function prepare( array $input ): Design {
        $design = $this->get();
        $clean  = $design->sanitize_input( $input );

        if ( $clean instanceof \WP_Error ) {
            throw new \InvalidArgumentException( $clean->get_error_message() );
        }

        $design->tokens     = $clean['tokens'];
        $design->custom_css = $clean['custom_css'];

        return $design;
    }

    /** Persists a design from prepare(), stamping updated_at. */
    private function store( Design $design ): Design {
        $design->updated_at = DateHelper::now_iso8601();

        $this->persist( $design );

        return $design;
    }

    private function persist( Design $design ): void {
        update_option( Design::OPTION_DESIGN, $design->to_option() );
    }
}
