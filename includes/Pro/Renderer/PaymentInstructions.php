<?php
namespace YayCheckout\Pro\Renderer;

use YayCheckout\Models\Layout;
use YayCheckout\Services\LayoutMarkupReader;

defined( 'ABSPATH' ) || exit;

/**
 * Pro: the Payment Instructions block's title and labels on top of what Direct bank transfer prints
 * (WC_Gateway_BACS::bank_details()). The title replaces WooCommerce's "Our bank details" through gettext,
 * and each label its account field's label through woocommerce_bacs_account_fields, so a Sort code label
 * also replaces the label WooCommerce picks by the order's country (BSB, Routing number, IFSC...). An empty
 * attribute keeps WooCommerce's text, translated. Other gateways print no title or labels, so they print
 * as they are. packages/blocks/src/pro/payment-instructions/edit.tsx mirrors the fields.
 */
class PaymentInstructions {

    /** WC_Gateway_BACS account field key => the block attribute holding its label. */
    public const FIELD_LABELS = [
        'bank_name'      => 'bankLabel',
        'account_number' => 'accountNumberLabel',
        'sort_code'      => 'sortCodeLabel',
        'iban'           => 'ibanLabel',
        'bic'            => 'bicLabel',
    ];

    /** Direct bank transfer's settings section, which the editor notice links to. */
    private const BACS_SETTINGS = 'admin.php?page=wc-settings&tab=checkout&section=bacs';

    /** Whether Direct bank transfer is turned off, so the block prints no bank details for it. */
    public static function bacs_disabled(): bool {
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        $bacs = WC()->payment_gateways()->payment_gateways()['bacs'] ?? null;

        return ! $bacs instanceof \WC_Payment_Gateway || 'yes' !== $bacs->enabled;
    }

    /**
     * Prints before the block's editor script whether Direct bank transfer is turned off, for its notice.
     * In the layout editor only, which is the one that offers the block.
     */
    public static function editor_data(): void {
        if ( Layout::POST_TYPE !== get_post_type() ) {
            return;
        }

        $handle = generate_block_asset_handle( 'yaycheckout/payment-instructions', 'editorScript' );

        if ( ! wp_script_is( $handle, 'registered' ) ) {
            return;
        }

        $data = [
            'needsSetup'  => self::bacs_disabled(),
            'settingsUrl' => admin_url( self::BACS_SETTINGS ),
        ];

        wp_add_inline_script( $handle, 'window.yayCheckoutBacsSetup = ' . wp_json_encode( $data ) . ';', 'before' );
    }

    /** Whether the block shows Direct bank transfer's heading. On unless turned off. */
    public static function shows_title( array $attributes ): bool {
        return ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];
    }

    /**
     * Runs $fire, which fires the gateway's thank-you hook, while the block's title and labels replace
     * WooCommerce's. Both filters are removed again before this returns.
     *
     * @param array    $attributes Block attributes.
     * @param callable $fire       Fires woocommerce_thankyou_<gateway>.
     */
    public static function with_labels( array $attributes, callable $fire ): void {
        $labels = [];

        foreach ( self::FIELD_LABELS as $field => $attribute ) {
            $label = LayoutMarkupReader::plain_text( $attributes, $attribute );

            if ( '' !== $label ) {
                $labels[ $field ] = $label;
            }
        }

        $relabel = static function ( $fields ) use ( $labels ) {
            if ( ! is_array( $fields ) ) {
                return $fields;
            }

            foreach ( $labels as $field => $label ) {
                if ( isset( $fields[ $field ] ) && is_array( $fields[ $field ] ) ) {
                    $fields[ $field ]['label'] = esc_html( $label );
                }
            }

            return $fields;
        };

        $title = self::shows_title( $attributes ) ? LayoutMarkupReader::plain_text( $attributes, 'title' ) : '';

        // PHP_INT_MAX: after any plugin that renames the fields, so the merchant's label wins.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's BACS filter.
        add_filter( 'woocommerce_bacs_account_fields', $relabel, PHP_INT_MAX );
        ThankYouPage::with_woocommerce_label_overrides( [ 'Our bank details' => $title ], $fire );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's BACS filter.
        remove_filter( 'woocommerce_bacs_account_fields', $relabel, PHP_INT_MAX );
    }

    /**
     * Drops the "Our bank details" heading Direct bank transfer prints, for the block's Show title off.
     * Leaves $html untouched when that heading is missing.
     */
    public static function without_title( string $html ): string {
        $stripped = preg_replace( '/<h2\b[^>]*\bwc-bacs-bank-details-heading\b[^>]*>.*?<\/h2>/is', '', $html, 1 );

        return is_string( $stripped ) ? $stripped : $html;
    }
}
