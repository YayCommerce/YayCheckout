<?php
/**
 * Server render for yaycheckout/slot.
 *
 * Named empty place. Plugins and Pro inject via yaycheckout_slot_{name}.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

$yaycheckout_slot_name = isset( $attributes['slotName'] ) && is_string( $attributes['slotName'] )
    ? sanitize_key( $attributes['slotName'] )
    : '';
$yaycheckout_wrapper   = get_block_wrapper_attributes(
    [
        'class'     => 'yaycheckout-slot',
        'data-slot' => $yaycheckout_slot_name,
    ]
);

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <?php
    if ( '' !== $yaycheckout_slot_name ) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Slot names are merchant-chosen; the prefix is yaycheckout_slot_.
        do_action( 'yaycheckout_slot_' . $yaycheckout_slot_name );
    }
    ?>
</div>
