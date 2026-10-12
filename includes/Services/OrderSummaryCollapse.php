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

    private const SUMMARY = 'yaycheckout/order-summary';
    private const HEADER  = 'yaycheckout/order-summary-header';
    private const DETAILS = 'yaycheckout/order-summary-details';
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
            $summary     = LayoutMarkupReader::first_block( $content, self::SUMMARY );
            $attrs       = null !== $summary ? $summary['attrs'] : [];
            $collapsible = true === ( $attrs['collapsible'] ?? false )
                && has_block( self::DETAILS, $content )
                && ( has_block( self::HEADER, $content ) || has_block( self::TOGGLE, $content ) );

            self::$memo[ $key ] = [
                'collapsible' => $collapsible,
                'open'        => ! $collapsible || 'open' === ( $attrs['initialState'] ?? 'collapsed' ),
            ];
        }

        return self::$memo[ $key ];
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
