<?php
/**
 * Prints a named empty slot that plugins fill through the yaycheckout_slot_{name} action.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_slot_name = isset( $attributes['slotName'] ) && is_string( $attributes['slotName'] )
    ? sanitize_key( $attributes['slotName'] )
    : '';

// Buffered so a slot nothing hooks into marks the block empty for HideWhenEmpty.
ob_start();

if ( '' !== $yaycheckout_slot_name ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Slot names are merchant-chosen; the prefix is yaycheckout_slot_.
    do_action( 'yaycheckout_slot_' . $yaycheckout_slot_name );
}

$yaycheckout_slot_content = (string) ob_get_clean();

$yaycheckout_wrapper = \YayCheckout\Renderer\HideWhenEmpty::wrapper(
    [
        'class'     => 'yaycheckout-slot',
        'data-slot' => $yaycheckout_slot_name,
    ],
    $attributes,
    '' === trim( $yaycheckout_slot_content )
);

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php echo $yaycheckout_slot_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whatever the slot's hooks print, as do_action() would. ?>
</div>
