<?php
namespace YayCheckout\Services;

use YayCheckout\Models\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Whether a layout's Order Summary is collapsible and starts open. Order Summary, its Header and Details,
 * and every Summary Toggle print from this one answer, because a Toggle outside the summary gets no block
 * context from it.
 */
class OrderSummaryCollapse {

    /** The id of the Summary Details wrapper, which every toggle controls; one Order Summary per layout. */
    public const DETAILS_ID = 'yaycheckout-order-summary-details';

    /** The child blocks whose presence puts an Order Summary in the new shape. */
    public const HEADER  = 'yaycheckout/order-summary-header';
    public const DETAILS = 'yaycheckout/order-summary-details';

    private const SUMMARY = 'yaycheckout/order-summary';
    private const TOGGLE  = 'yaycheckout/order-summary-toggle';

    /** @var array<string, array{collapsible: bool, open: bool}> Keyed by the markup's md5. */
    private static $memo = [];

    /**
     * Collapsible needs details to hide and a header or a Toggle to open them with; without both, the details
     * (if any) always show and every Toggle prints plainly.
     * Stored layouts lack both attributes: not collapsible, and starting collapsed once turned on.
     *
     * @return array{collapsible: bool, open: bool}
     */
    public static function state( string $content ): array {
        $key = md5( $content );

        if ( ! isset( self::$memo[ $key ] ) ) {
            // has_block() only searches the text, so a layout that can't collapse is never parsed.
            $can_collapse = has_block( self::DETAILS, $content )
                && ( has_block( self::HEADER, $content ) || has_block( self::TOGGLE, $content ) );
            $summary      = $can_collapse ? LayoutMarkupReader::first_block( $content, self::SUMMARY ) : null;
            $attrs        = null !== $summary ? $summary['attrs'] : [];
            $collapsible  = true === ( $attrs['collapsible'] ?? false );

            self::$memo[ $key ] = [
                'collapsible' => $collapsible,
                'open'        => ! $collapsible || 'open' === ( $attrs['initialState'] ?? 'collapsed' ),
            ];
        }

        return self::$memo[ $key ];
    }

    /** Whether a Summary Toggle sits anywhere in these parsed blocks; a header without one is its own tab stop. */
    public static function has_toggle( array $blocks ): bool {
        return in_array( self::TOGGLE, array_column( LayoutMarkupReader::flatten( $blocks ), 'blockName' ), true );
    }

    /** Order Summary's Label while the merchant leaves it empty. */
    public static function default_label(): string {
        return __( 'Your order', 'yaycheckout' );
    }

    /**
     * The state of the rendered layout, as state(); without one, not collapsible.
     *
     * @return array{collapsible: bool, open: bool}
     */
    public static function of_layout( ?Layout $layout ): array {
        return null === $layout ? [
            'collapsible' => false,
            'open'        => true,
        ] : self::state( $layout->content );
    }
}
