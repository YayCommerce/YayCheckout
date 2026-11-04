<?php
namespace YayCheckout\Renderer;

use YayCheckout\Models\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * The classes a block that may sit outside Checkout puts on its wrapper, so checkout-base.css and the blocks'
 * styles draw its markup as inside Checkout, where they read `:is(.yaycheckout-checkout, .yaycheckout-scope)`.
 * With the layout's floating labels it also takes FLOATING_LABELS_CLASS, as Checkout takes
 * yaycheckout-checkout--floating-labels. packages/blocks/src/shared/checkout-scope.ts mirrors classes().
 */
final class CheckoutScope {

    public const CLASS_NAME = 'yaycheckout-scope';

    public const FLOATING_LABELS_CLASS = 'yaycheckout-scope--floating-labels';

    /**
     * The scope classes for $layout, or for the served checkout layout without one, space-separated.
     *
     * @param Layout|null $layout The layout the page renders, when it isn't the checkout's (Pro's thank-you page).
     */
    public static function classes( ?Layout $layout = null ): string {
        $layout = $layout ?? CheckoutPage::get_instance()->layout();

        return null !== $layout && $layout->design->has_floating_labels()
            ? self::CLASS_NAME . ' ' . self::FLOATING_LABELS_CLASS
            : self::CLASS_NAME;
    }
}
