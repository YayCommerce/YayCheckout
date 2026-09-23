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
     * Validates and persists the applied design, stamping updated_at; stored
     * user presets are carried over untouched.
     *
     * @param array $input Raw { tokens, preset_id, custom_css } body.
     * @throws \InvalidArgumentException When the body fails Design::sanitize_input().
     */
    public function save( array $input ): Design {
        $design = $this->get();
        $clean  = $design->sanitize_input( $input );

        if ( $clean instanceof \WP_Error ) {
            throw new \InvalidArgumentException( $clean->get_error_message() );
        }

        $design->tokens     = $clean['tokens'];
        $design->preset_id  = $clean['preset_id'];
        $design->custom_css = $clean['custom_css'];
        $design->updated_at = DateHelper::now_iso8601();

        $this->persist( $design );

        return $design;
    }

    /**
     * Appends a merchant preset. The id is an ASCII slug of the name, suffixed
     * until unique, so it stays readable in the option. Saving the applied
     * tokens under a name makes that preset the applied one.
     *
     * @param array $input Raw { name, tokens } body; name must already be plain text.
     * @throws \InvalidArgumentException When the body fails Design::sanitize_preset().
     */
    public function add_preset( array $input ): Design {
        $design = $this->get();
        $clean  = Design::sanitize_preset( $input );

        if ( $clean instanceof \WP_Error ) {
            throw new \InvalidArgumentException( $clean->get_error_message() );
        }

        $base = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $clean['name'] ) ), '-' );
        $base = '' === $base ? 'preset' : $base;
        $id   = $base;
        $n    = 1;

        while ( $design->has_preset( $id ) ) {
            ++$n;
            $id = $base . '-' . $n;
        }

        $design->presets[] = [
            'id'      => $id,
            'name'    => $clean['name'],
            'builtin' => false,
            'tokens'  => $clean['tokens'],
        ];

        if ( $clean['tokens'] === $design->tokens ) {
            $design->preset_id = $id;
        }

        $this->persist( $design );

        return $design;
    }

    /**
     * Drops a merchant preset; built-ins cannot be removed. A design that had
     * it applied falls back to no preset, its tokens untouched.
     *
     * @throws \InvalidArgumentException When $id is not a merchant preset.
     */
    public function delete_preset( string $id ): Design {
        $design = $this->get();
        $index  = $design->user_preset_index( $id );

        if ( null === $index ) {
            throw new \InvalidArgumentException( __( 'Unknown preset.', 'yaycheckout' ) );
        }

        array_splice( $design->presets, $index, 1 );

        if ( $design->preset_id === $id ) {
            $design->preset_id = null;
        }

        $this->persist( $design );

        return $design;
    }

    private function persist( Design $design ): void {
        update_option( Design::OPTION_DESIGN, $design->to_option() );
    }
}
