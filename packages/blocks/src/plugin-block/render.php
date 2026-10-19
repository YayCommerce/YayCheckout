<?php
/**
 * Prints the entry's shortcode, when it has one this site registers: the free edition of the other plugin fills the
 * spot that way, while its real block is the merchant's to add from the editor card (PluginBlockCatalogue).
 * Otherwise nothing.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused; this block is a leaf.
 * @var WP_Block $block      Block instance.
 */

use YayCheckout\Helpers\PluginBlockCatalogue;

defined( 'ABSPATH' ) || exit;

$yaycheckout_target = $attributes['target'] ?? '';
$yaycheckout_entry  = current( wp_list_filter( PluginBlockCatalogue::entries(), [ 'target' => $yaycheckout_target ] ) );
$yaycheckout_tag    = is_array( $yaycheckout_entry ) ? $yaycheckout_entry['shortcode'] : '';

if ( ! shortcode_exists( $yaycheckout_tag ) ) {
    return;
}

$yaycheckout_output = do_shortcode( '[' . $yaycheckout_tag . ']' );

if ( '' === trim( $yaycheckout_output ) ) {
    return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>><?php echo $yaycheckout_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The plugin's own shortcode markup. ?></div>
