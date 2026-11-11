<?php
namespace YayCheckout\Services;

use YayCheckout\Helpers\PluginBlockCatalogue;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Renderer\BlockVisibility;
use YayCheckout\Renderer\PinToBottom;
use YayCheckout\Renderer\ShowableFields;
use YayCheckout\Renderer\WcHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Run test's static check: what in a layout, on this store, would stop a shopper ordering (an error) or may
 * break part of the checkout (a warning). Pure: the store facts come in from Helpers\LayoutCheckFacts.
 *
 * Unlike LayoutMarkupReader::publish_error(), which it includes, nothing here refuses a save or an Activate:
 * these rules depend on store settings, and a layout that works today must keep activating.
 */
class LayoutCheck {

    public const ERROR   = 'error';
    public const WARNING = 'warning';
    public const INFO    = 'info';

    /** Every code run() may report. */
    public const CODES = [
        'publish_rule',
        'missing_place_order',
        'place_order_not_in_last_step',
        'steps_without_next_button',
        'missing_terms',
        'missing_payment',
        'missing_shipping_methods',
        'hidden_required_field',
        'logged_out_without_login',
        'missing_email',
        'shipping_needs_setup',
        'payment_needs_setup',
        'block_only_gateway',
        'express_pay_no_buttons',
        'plugin_spot_unavailable',
        'live_value_missing_field',
        'visibility_target_missing',
        'duplicate_wc_hook',
        'multiple_pinned_groups',
        'before_form_hook_unplaced',
        'custom_css_rejected',
    ];

    /** The view a finding's block sits in: the main checkout or the guest's logged-out view. */
    public const VIEW_MAIN       = 'main';
    public const VIEW_LOGGED_OUT = 'logged_out';

    private const CHECKOUT         = 'yaycheckout/checkout';
    private const EMAIL            = 'yaycheckout/email';
    private const EXTRA_FIELD      = 'yaycheckout/extra-field';
    private const ORDER_NOTE       = 'yaycheckout/order-note';
    private const PLACE_ORDER      = 'yaycheckout/place-order';
    private const TERMS            = 'yaycheckout/terms';
    private const PAYMENT          = 'yaycheckout/payment';
    private const SHIPPING_METHODS = 'yaycheckout/shipping-methods';
    private const EXPRESS_PAY      = 'yaycheckout/express-pay';
    private const LOGIN            = 'yaycheckout/login';
    private const STEPS            = 'yaycheckout/steps';
    private const STEP             = 'yaycheckout/step';
    private const STEP_BUTTON      = 'yaycheckout/step-button';
    private const GROUP            = 'core/group';

    /** The hook every template places a Hook block for, where plugins print their checkout notices. */
    private const BEFORE_FORM_HOOK = 'woocommerce_before_checkout_form';

    /** A live value chip's key in saved markup: data-yaycheckout-value="field:<key>" (live-value/constants.ts). */
    private const LIVE_FIELD_PATTERN = '/data-yaycheckout-value=(["\'])field:([^"\']+)\1/';

    /**
     * Every finding for $layout, errors first, then warnings, then info, each in document order.
     *
     * @param string $logged_out_markup What a guest who must log in sees: the saved view, else its template's.
     * @param array  $facts             Helpers\LayoutCheckFacts::current().
     * @return array<int, array{code: string, severity: string, message: string, block: array{view: string, name: string, index: int}|null, link: string}>
     */
    public static function run( Layout $layout, string $logged_out_markup, array $facts ): array {
        $markup   = $layout->content;
        $blocks   = self::indexed( parse_blocks( $markup ) );
        $names    = array_column( $blocks, 'blockName' );
        $urls     = (array) ( $facts['settings_urls'] ?? [] );
        $findings = [];

        $publish = LayoutMarkupReader::publish_error( $markup, (array) ( $facts['locked_blocks'] ?? [] ) );

        if ( '' !== $publish ) {
            $findings[] = self::finding( 'publish_rule', self::ERROR, $publish );
        }

        // Without a Checkout block nothing renders, and publish_rule already says so.
        if ( in_array( self::CHECKOUT, $names, true ) ) {
            $findings = array_merge(
                $findings,
                self::required_blocks( $names, $facts, $urls ),
                self::steps( $blocks ),
                self::hidden_required_fields( parse_blocks( $markup ) ),
                self::logged_out( $logged_out_markup, $facts ),
                self::store( $facts, $urls ),
                self::layout_settings( $layout, $blocks, $facts )
            );
        }

        $order = [
            self::ERROR   => 0,
            self::WARNING => 1,
            self::INFO    => 2,
        ];

        // usort() isn't stable before PHP 8, so the original position breaks ties.
        $keyed = array_map( null, $findings, array_keys( $findings ) );
        usort(
            $keyed,
            static function ( array $a, array $b ) use ( $order ): int {
                return [ $order[ $a[0]['severity'] ], $a[1] ] <=> [ $order[ $b[0]['severity'] ], $b[1] ];
            }
        );

        return array_column( $keyed, 0 );
    }

    /**
     * The blocks every order needs, and those this store's settings need.
     *
     * @param string[] $names Block names in document order.
     */
    private static function required_blocks( array $names, array $facts, array $urls ): array {
        $findings = [];

        if ( ! in_array( self::PLACE_ORDER, $names, true ) ) {
            $findings[] = self::finding( 'missing_place_order', self::ERROR, __( 'Add a Place Order button: without it, shoppers can\'t submit the checkout.', 'yaycheckout' ) );
        }

        if ( ! in_array( self::EMAIL, $names, true ) ) {
            $findings[] = self::finding( 'missing_email', self::ERROR, __( 'Add an Email field: without it, orders have no email address for receipts.', 'yaycheckout' ) );
        }

        if ( ! in_array( self::PAYMENT, $names, true ) ) {
            $findings[] = self::finding( 'missing_payment', self::ERROR, __( 'Add a Payment block: without it, shoppers can\'t choose how to pay, and orders that need payment fail.', 'yaycheckout' ) );
        }

        if ( ! empty( $facts['ships'] ) && ! in_array( self::SHIPPING_METHODS, $names, true ) ) {
            $findings[] = self::finding( 'missing_shipping_methods', self::ERROR, __( 'Add a Shipping Methods block: your store ships, and without it shoppers can\'t choose a shipping option.', 'yaycheckout' ) );
        }

        if ( ! empty( $facts['terms_page'] ) && ! in_array( self::TERMS, $names, true ) ) {
            $findings[] = self::finding(
                'missing_terms',
                self::ERROR,
                __( 'Add a Terms & Privacy block: your store has a Terms and conditions page, and WooCommerce refuses every order whose terms checkbox wasn\'t shown and ticked.', 'yaycheckout' ),
                null,
                (string) ( $urls['terms'] ?? '' )
            );
        }

        return $findings;
    }

    /**
     * The first Steps block with two or more steps: Place Order must sit in its last step or outside it,
     * and every step but the last needs a Next Step Button, inside it or shared outside every step.
     *
     * @param array $blocks indexed() blocks.
     */
    private static function steps( array $blocks ): array {
        foreach ( $blocks as $block ) {
            if ( self::STEPS !== $block['blockName'] ) {
                continue;
            }

            $parent = self::key( $block );
            $steps  = array_values(
                array_filter(
                    $blocks,
                    static fn( array $step ): bool => self::STEP === $step['blockName'] && end( $step['ancestors'] ) === $parent
                )
            );

            if ( count( $steps ) >= 2 ) {
                return self::steps_findings( $blocks, $block, array_map( [ self::class, 'key' ], array_slice( $steps, 0, -1 ) ) );
            }
        }

        return [];
    }

    /**
     * @param array    $blocks  indexed() blocks.
     * @param array    $steps   The Steps block.
     * @param string[] $earlier key() of each step but the last.
     */
    private static function steps_findings( array $blocks, array $steps, array $earlier ): array {
        $findings = [];

        foreach ( $blocks as $block ) {
            if ( self::PLACE_ORDER === $block['blockName'] && [] !== array_intersect( $block['ancestors'], $earlier ) ) {
                $findings[] = self::finding(
                    'place_order_not_in_last_step',
                    self::ERROR,
                    __( 'Move Place Order into the last step: shoppers would submit before they reach the payment step.', 'yaycheckout' ),
                    self::ref( $block )
                );
            }
        }

        // A Next Step Button inside a step advances only that step; one outside every step advances whichever is open.
        $buttons = array_filter( $blocks, static fn( array $block ): bool => self::STEP_BUTTON === $block['blockName'] );
        $shared  = array_filter( $buttons, static fn( array $button ): bool => ! self::in_a_step( $button ) );

        if ( [] === $shared ) {
            foreach ( $blocks as $block ) {
                if ( ! in_array( self::key( $block ), $earlier, true ) ) {
                    continue;
                }

                $key = self::key( $block );
                $own = array_filter( $buttons, static fn( array $button ): bool => in_array( $key, $button['ancestors'], true ) );

                if ( [] === $own ) {
                    $findings[] = self::finding(
                        'steps_without_next_button',
                        self::ERROR,
                        __( 'Add a Next Step Button to this step: without one, shoppers can\'t move on to the next step.', 'yaycheckout' ),
                        self::ref( $block )
                    );
                }
            }
        }

        return $findings;
    }

    /** Whether a block sits inside a Step block. */
    private static function in_a_step( array $block ): bool {
        foreach ( $block['ancestors'] as $ancestor ) {
            if ( 0 === strpos( $ancestor, self::STEP . '#' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Required fields at or below a block with a Visibility setting. A hidden required field still blocks the
     * order (decisions/2026-10-13-block-visibility-in-block-markup.md), so the shopper may be stuck at Place Order.
     * A condition that hides the field only when WooCommerce wouldn't require it is safe (hides_only_when_not_required()).
     *
     * @param array $tree parse_blocks() output.
     */
    private static function hidden_required_fields( array $tree ): array {
        $findings = [];

        foreach ( self::indexed( $tree ) as $block ) {
            if ( ! self::is_required_field( $block ) ) {
                continue;
            }

            $risky = array_filter(
                $block['conditions'],
                static fn( array $setting ): bool => ! self::hides_only_when_not_required( $setting, $block )
            );

            if ( [] === $risky ) {
                continue;
            }

            $label = LayoutMarkupReader::plain_text( $block['attrs'], 'label', LayoutMarkupReader::text( $block['attrs'], 'fieldKey', __( 'Email', 'yaycheckout' ) ) );

            $findings[] = self::finding(
                'hidden_required_field',
                self::WARNING,
                sprintf(
                    /* translators: %s: the field's label. */
                    __( '"%s" is required but sits in a block that a condition can hide. If it is hidden at Place Order, the shopper is told to fill in a field they can\'t see. Make it optional or move it out.', 'yaycheckout' ),
                    $label
                ),
                self::ref( $block )
            );
        }

        return $findings;
    }

    /**
     * Whether a Visibility setting hides a required field only when WooCommerce wouldn't require it anyway.
     * Each rule must be one of:
     * - "show when the field itself is not empty" (or "hide when empty"): a Field is empty only while the
     *   country's locale hides it, and WooCommerce then doesn't require it (the templates' State column);
     * - "show when the cart needs shipping", around a shipping address field: WooCommerce checks shipping
     *   fields only for a cart that needs shipping (the Multi-step wizard's shipping address).
     * Rules are ANDed, so any other rule could hide the block for another reason.
     *
     * @param array $setting A BlockVisibility::normalize() setting.
     * @param array $field   The required field, as indexed() gives it.
     */
    private static function hides_only_when_not_required( array $setting, array $field ): bool {
        $block_id = LayoutMarkupReader::text( $field['attrs'], 'blockId' );
        $shipping = 0 === strpos( LayoutMarkupReader::text( $field['attrs'], 'fieldKey' ), 'shipping_' );
        $show     = 'show' === $setting['action'];

        foreach ( $setting['rules'] as $rule ) {
            $self_empty = 'block' === $rule['type'] && '' !== $block_id && $block_id === $rule['block']
                && ( $show ? 'not_empty' : 'empty' ) === $rule['is'];
            $ships      = 'needs_shipping' === $rule['type'] && $show && $shipping;

            if ( ! $self_empty && ! $ships ) {
                return false;
            }
        }

        return true;
    }

    /** A block WooCommerce or the extra field rules will require a value for. */
    private static function is_required_field( array $block ): bool {
        if ( self::EMAIL === $block['blockName'] ) {
            return true; // RequiredFieldOverlay always requires an email field.
        }

        $fields = [ LayoutMarkupReader::FIELD, self::EXTRA_FIELD, self::ORDER_NOTE ];

        return in_array( $block['blockName'], $fields, true ) && ! empty( $block['attrs']['required'] );
    }

    /** A guest who must log in gets no form, so the logged-out view needs a way to log in. */
    private static function logged_out( string $markup, array $facts ): array {
        if ( empty( $facts['login_required'] ) || has_block( self::LOGIN, $markup ) ) {
            return [];
        }

        return [
            self::finding(
                'logged_out_without_login',
                self::ERROR,
                __( 'Add a Login block to the Guest view: your store makes guests log in before checkout, and this view gives them no way to.', 'yaycheckout' ),
                // The Login block is what's missing, so the ref names the view, at its Checkout block.
                [
                    'view'  => self::VIEW_LOGGED_OUT,
                    'name'  => self::CHECKOUT,
                    'index' => 0,
                ]
            ),
        ];
    }

    /** Store settings that break checkout whatever the layout holds. */
    private static function store( array $facts, array $urls ): array {
        $findings = [];

        if ( ! empty( $facts['shipping_needs_setup'] ) ) {
            $findings[] = self::finding( 'shipping_needs_setup', self::WARNING, __( 'No shipping zone or method is set up, so shoppers check out without choosing shipping.', 'yaycheckout' ), null, (string) ( $urls['shipping'] ?? '' ) );
        }

        if ( ! empty( $facts['payment_needs_setup'] ) ) {
            $findings[] = self::finding( 'payment_needs_setup', self::WARNING, __( 'No payment method is enabled, so shoppers cannot place an order.', 'yaycheckout' ), null, (string) ( $urls['payment'] ?? '' ) );
        }

        foreach ( (array) ( $facts['block_only_gateways'] ?? [] ) as $title ) {
            $findings[] = self::finding(
                'block_only_gateway',
                self::WARNING,
                sprintf(
                    /* translators: %s: payment method name. */
                    __( '%s works only with WooCommerce\'s Checkout block, so it won\'t show on this layout.', 'yaycheckout' ),
                    (string) $title
                ),
                null,
                (string) ( $urls['payment'] ?? '' )
            );
        }

        return $findings;
    }

    /**
     * Settings inside the layout that point at nothing, repeat, or are refused.
     *
     * @param array $blocks indexed() blocks.
     */
    private static function layout_settings( Layout $layout, array $blocks, array $facts ): array {
        $findings  = [];
        $block_ids = [];
        $hooks     = [];
        $pinned    = [];
        $spots     = array_flip( PluginBlockCatalogue::targets() );
        $showable  = ShowableFields::labels( $layout->content );
        $states    = (array) ( $facts['plugin_spot_states'] ?? [] );
        $missing   = [];

        foreach ( $blocks as $block ) {
            $id = LayoutMarkupReader::text( $block['attrs'], 'blockId' );

            if ( '' !== $id ) {
                $block_ids[ $id ] = true;
            }
        }

        foreach ( $blocks as $block ) {
            $name  = $block['blockName'];
            $attrs = $block['attrs'];

            if ( self::EXPRESS_PAY === $name && empty( $facts['express_pay_has_buttons'] ) ) {
                $findings[] = self::finding( 'express_pay_no_buttons', self::WARNING, __( 'No enabled payment method shows express buttons, so the Express Pay block stays empty.', 'yaycheckout' ), self::ref( $block ) );
            }

            $target = PluginBlockCatalogue::spot_for( $block, array_keys( $spots ) );

            if ( '' !== $target && 'shortcode' !== ( $states[ $target ] ?? '' ) ) {
                $findings[] = self::finding( 'plugin_spot_unavailable', self::WARNING, __( 'This plugin block prints nothing: its plugin isn\'t installed, active or licensed, or its feature is off.', 'yaycheckout' ), self::ref( $block ) );
            }

            if ( WcHooks::BLOCK === $name ) {
                $hooks[ LayoutMarkupReader::text( $attrs, 'hook' ) ][] = $block;
            }

            if ( self::GROUP === $name && ! empty( $attrs[ PinToBottom::ATTRIBUTE ] ) ) {
                $pinned[] = $block;
            }

            $setting = BlockVisibility::normalize( $attrs[ BlockVisibility::ATTRIBUTE ] ?? null );

            foreach ( null === $setting ? [] : $setting['rules'] as $rule ) {
                if ( 'block' === $rule['type'] && ! isset( $block_ids[ $rule['block'] ] ) ) {
                    $findings[] = self::finding( 'visibility_target_missing', self::WARNING, __( 'A visibility condition on this block depends on a block that is no longer in the layout, so it counts that block as empty.', 'yaycheckout' ), self::ref( $block ) );
                }
            }

            foreach ( self::live_field_keys( $block ) as $key ) {
                if ( ! isset( $showable[ $key ] ) && ! isset( $missing[ $key ] ) ) {
                    $missing[ $key ] = true;
                    $findings[]      = self::finding(
                        'live_value_missing_field',
                        self::WARNING,
                        sprintf(
                            /* translators: %s: field key. */
                            __( 'A live value shows the field "%s", which is no longer in the layout, so it prints empty.', 'yaycheckout' ),
                            $key
                        ),
                        self::ref( $block )
                    );
                }
            }
        }

        foreach ( $hooks as $hook => $placed ) {
            if ( '' !== $hook && count( $placed ) > 1 ) {
                $findings[] = self::finding(
                    'duplicate_wc_hook',
                    self::WARNING,
                    sprintf(
                        /* translators: %s: WooCommerce hook name. */
                        __( 'Two WooCommerce Hook blocks fire %s, so what plugins print there shows twice.', 'yaycheckout' ),
                        $hook
                    ),
                    self::ref( $placed[1] )
                );
            }
        }

        if ( count( $pinned ) > 1 ) {
            $findings[] = self::finding( 'multiple_pinned_groups', self::WARNING, __( 'More than one Group is pinned to the bottom of the screen, so they cover each other.', 'yaycheckout' ), self::ref( $pinned[1] ) );
        }

        if ( ! empty( $facts['before_form_hooked'] ) && ! isset( $hooks[ self::BEFORE_FORM_HOOK ] ) ) {
            $findings[] = self::finding( 'before_form_hook_unplaced', self::WARNING, __( 'A plugin prints on woocommerce_before_checkout_form, but this layout has no WooCommerce Hook block for it, so that output never shows.', 'yaycheckout' ) );
        }

        if ( is_wp_error( LayoutDesign::sanitize_custom_css( $layout->design->custom_css ) ) ) {
            $findings[] = self::finding( 'custom_css_rejected', self::WARNING, __( 'This layout\'s custom CSS is refused (too large, or holds markup), so it is not printed.', 'yaycheckout' ) );
        }

        return $findings;
    }

    /**
     * Field keys of the live value chips in a block's saved HTML and its string attributes, where Step,
     * Place Order and the other display texts keep theirs.
     *
     * @return string[]
     */
    private static function live_field_keys( array $block ): array {
        $texts = array_merge( [ (string) ( $block['innerHTML'] ?? '' ) ], array_filter( $block['attrs'], 'is_string' ) );
        $keys  = [];

        foreach ( $texts as $text ) {
            if ( false !== strpos( $text, 'field:' ) && preg_match_all( self::LIVE_FIELD_PATTERN, $text, $matches ) ) {
                $keys = array_merge( $keys, $matches[2] );
            }
        }

        return array_values( array_unique( $keys ) );
    }

    /**
     * Blocks in LayoutMarkupReader::flatten() order (parents first), each with `index` (its position among
     * blocks of its name, which the editor maps back to a client id), `ancestors` (key() of each, outermost
     * first) and `conditions` (the cleaned Visibility setting of it and each ancestor that has one).
     *
     * @param array $tree parse_blocks() output.
     */
    private static function indexed( array $tree ): array {
        $counts = [];

        return self::walk( $tree, $counts, [], [] );
    }

    /**
     * @param array              $tree       Blocks at one level.
     * @param array<string, int> $counts     Blocks of each name met so far, shared by the whole walk.
     * @param string[]           $ancestors  key() of each enclosing block.
     * @param array              $conditions BlockVisibility::normalize() settings of the enclosing blocks.
     */
    private static function walk( array $tree, array &$counts, array $ancestors, array $conditions ): array {
        $flat = [];

        foreach ( $tree as $block ) {
            if ( empty( $block['blockName'] ) ) {
                continue;
            }

            $block['attrs']      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
            $block['index']      = $counts[ $block['blockName'] ] ?? 0;
            $block['ancestors']  = $ancestors;
            $setting             = BlockVisibility::normalize( $block['attrs'][ BlockVisibility::ATTRIBUTE ] ?? null );
            $block['conditions'] = null === $setting ? $conditions : array_merge( $conditions, [ $setting ] );

            $counts[ $block['blockName'] ] = $block['index'] + 1;

            $flat[] = $block;
            $flat   = array_merge( $flat, self::walk( $block['innerBlocks'] ?? [], $counts, array_merge( $ancestors, [ self::key( $block ) ] ), $block['conditions'] ) );
        }

        return $flat;
    }

    /** A block's identity within the layout: its name and index. */
    private static function key( array $block ): string {
        return $block['blockName'] . '#' . $block['index'];
    }

    /** @param array $block An indexed() block in the main view. */
    private static function ref( array $block ): array {
        return [
            'view'  => self::VIEW_MAIN,
            'name'  => $block['blockName'],
            'index' => $block['index'],
        ];
    }

    /** @param array{view: string, name: string, index: int}|null $block */
    private static function finding( string $code, string $severity, string $message, ?array $block = null, string $link = '' ): array {
        return [
            'code'     => $code,
            'severity' => $severity,
            'message'  => $message,
            'block'    => $block,
            'link'     => $link,
        ];
    }
}
