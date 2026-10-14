<?php
/**
 * Server render for yaycheckout/step.
 *
 * The first step is current. Later steps are locked. Their fields stay in
 * the form so one Place order posts them; the steps script opens the next
 * step.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks, already rendered by core.
 * @var WP_Block $block      Block instance.
 *
 * @package yaycommerce/yaycheckout
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'yaycheckout_step_index' ) ) {
    /**
     * Next step number for this request.
     *
     * Pass true to zero it. Step templates run before the Steps template,
     * so Steps resets the count after its own steps have rendered.
     */
    function yaycheckout_step_index( bool $reset = false ): int {
        static $index = 0;

        if ( $reset ) {
            $index = 0;

            return 0;
        }

        ++$index;

        return $index;
    }
}

$yaycheckout_index = yaycheckout_step_index();
$yaycheckout_state = 1 === $yaycheckout_index ? 'current' : 'locked';
$yaycheckout_title = \YayCheckout\Renderer\CheckoutStepsProgress::step_title_html( $attributes );

$yaycheckout_wrapper = get_block_wrapper_attributes(
    [
        'class'      => 'yaycheckout-step' . \YayCheckout\Renderer\BlockGap::wrapper_class( $attributes, 'yaycheckout/step', 'yaycheckout-step', ' > .yaycheckout-step__body', true ),
        'data-state' => $yaycheckout_state,
        'data-step'  => (string) $yaycheckout_index,
    ]
);

?>
<div <?php echo $yaycheckout_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attribute string; running it through kses would corrupt it. ?>>
    <div class="yaycheckout-step__header">
        <span class="yaycheckout-step__index"><?php echo esc_html( (string) $yaycheckout_index ); ?></span>
        <span class="yaycheckout-step__title"><?php echo $yaycheckout_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered by wp_kses in LayoutMarkupReader::rich_text(). ?></span>
        <p class="yaycheckout-step__summary" hidden></p>
        <button type="button" class="yaycheckout-step__edit" hidden><?php esc_html_e( 'Edit', 'yaycheckout' ); ?></button>
    </div>
    <div class="yaycheckout-step__body">
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block markup, already rendered and escaped by core. ?>
    </div>
</div>
