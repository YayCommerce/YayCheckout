<?php
/**
 * Prints a named slot that plugins fill through the yaycheckout_slot_{name} action, and nothing while none does.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_slot_name = isset( $attributes['slotName'] ) && is_string( $attributes['slotName'] )
    ? sanitize_key( $attributes['slotName'] )
    : '';

// Buffered so a slot nothing hooks into prints nothing.
ob_start();

if ( '' !== $yaycheckout_slot_name ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Slot names are merchant-chosen; the prefix is yaycheckout_slot_.
    do_action( 'yaycheckout_slot_' . $yaycheckout_slot_name );
}

$yaycheckout_slot_content = (string) ob_get_clean();

// Prints nothing when no hook printed anything, so the merchant's padding, border or background don't draw an
// empty box. HideWhenEmpty counts a block that prints nothing as empty.
if ( '' === trim( $yaycheckout_slot_content ) ) {
    return;
}

$yaycheckout_wrapper = \YayCheckout\Renderer\HideWhenEmpty::wrapper(
    [
        'class'     => 'yaycheckout-slot',
        'data-slot' => $yaycheckout_slot_name,
    ],
    $attributes
);

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_slot_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whatever the slot's hooks print, as do_action() would. ?>
</div>
