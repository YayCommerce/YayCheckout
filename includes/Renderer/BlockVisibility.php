<?php
namespace YayCheckout\Renderer;

use YayCheckout\Helpers\JsonElementHelper;
use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Shows or hides any block in a layout by the open step, the cart, login and whether a watchable block
 * is empty (the block attribute yaycheckoutVisibility; BlockEmptiness owns what empty means). PHP owns the rules; packages/blocks/src/shared/visibility-rules.ts mirrors
 * normalize() and is_visible() for the storefront script and the editor preview.
 *
 * The server decides each block with step 1 open and prints a failing block with HIDDEN_CLASS. Every
 * conditional block carries its cleaned setting in DATA_ATTR, and the footer prints the cart state, which
 * update_order_review swaps as a fragment, so the storefront script re-decides on step and cart changes.
 * A setting with a "block" rule can't be decided until its target renders, which may be later in the
 * layout, so finalize() decides it over the whole layout's HTML.
 */
class BlockVisibility {

    use SingletonTrait;

    /** The block attribute holding the setting. Mirrored by ATTRIBUTE in visibility-rules.ts. */
    public const ATTRIBUTE = 'yaycheckoutVisibility';

    /** On a conditional block's first tag: its cleaned setting as JSON. Mirrored by DATA_ATTR in visibility-rules.ts. */
    public const DATA_ATTR = 'data-yaycheckout-visibility';

    /** On a conditional block while hidden; checkout-base.css hides it. Mirrored by HIDDEN_CLASS in visibility-rules.ts. */
    public const HIDDEN_CLASS = 'yaycheckout-hidden-by-visibility';

    /** The id of the footer's JSON state element, and with `#` its update_order_review fragment. Mirrored by STATE_ID in visibility-rules.ts. */
    public const STATE_ID = 'yaycheckout-visibility-state';

    /** The storefront script, packages/blocks/src/visibility/view.ts; registered by BlockRegistry. */
    public const SCRIPT = 'yaycheckout-visibility';

    /** The editor script, packages/blocks/src/visibility/index.tsx; enqueued by BlockRegistry. */
    public const EDITOR_SCRIPT = 'yaycheckout-visibility-editor';

    /** The Conditional tag and preview dimming in the layout editor's canvas, from packages/blocks/src/visibility/editor.css. */
    public const EDITOR_STYLE = 'yaycheckout-visibility-editor';

    /** Blocks that never take the setting: step navigation can't skip a step. Mirrored by EXCLUDED in visibility-rules.ts. */
    public const EXCLUDED = [ 'yaycheckout/checkout', 'yaycheckout/steps', 'yaycheckout/step' ];

    /** Mirrored by RULE_TYPES in visibility-rules.ts. */
    public const RULE_TYPES = [ 'step', 'needs_shipping', 'has_coupon', 'subtotal', 'logged_in', 'block' ];

    /** Set once filter_block() decorated a block, so the footer prints the state the script reads. */
    private $rendered = false;

    /** Set once filter_block() left a block with a "block" rule for finalize() to decide. */
    private $deferred = false;

    protected function __construct() {
        add_filter( 'render_block', [ $this, 'filter_block' ], 10, 2 );
        add_action( 'wp_footer', [ $this, 'print_state' ] );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 20 );
    }

    /**
     * The cleaned setting: the action and its rules with only their known keys, amount as a float and step
     * as an int unless "last". Null for an absent, empty or malformed setting, which leaves the block shown.
     *
     * @param mixed $value The block attribute as parsed.
     * @return array{action: string, rules: array<int, array>}|null
     */
    public static function normalize( $value ): ?array {
        if ( ! is_array( $value ) || ! in_array( $value['action'] ?? null, [ 'show', 'hide' ], true ) ) {
            return null;
        }

        $rules = $value['rules'] ?? null;

        if ( ! is_array( $rules ) || [] === $rules || ! array_is_list( $rules ) ) {
            return null;
        }

        $clean = [];

        foreach ( $rules as $rule ) {
            $type = is_array( $rule ) ? ( $rule['type'] ?? null ) : null;

            if ( ! in_array( $type, self::RULE_TYPES, true ) ) {
                return null;
            }

            $kept = self::normalize_rule( $type, $rule );
            // Each type once, except "block", which may repeat for different blocks.
            $key = null !== $kept && 'block' === $type ? 'block:' . $kept['block'] : $type;

            if ( null === $kept || isset( $clean[ $key ] ) ) {
                return null;
            }

            $clean[ $key ] = $kept;
        }

        return [
            'action' => $value['action'],
            'rules'  => array_values( $clean ),
        ];
    }

    /**
     * What the rules test for the current cart and shopper. The subtotal is the cart's as the cart shows it,
     * rounded to the store's decimals, so the script compares it as is. Without a cart, all false and 0.
     *
     * @return array{needs_shipping: bool, has_coupon: bool, subtotal: float, logged_in: bool}
     */
    public static function state(): array {
        $cart = function_exists( 'WC' ) && WC()->cart instanceof \WC_Cart ? WC()->cart : null;

        return [
            'needs_shipping' => null !== $cart && $cart->needs_shipping(),
            'has_coupon'     => null !== $cart && ! empty( $cart->get_applied_coupons() ),
            'subtotal'       => round( LiveValues::money()['cart_subtotal'] ?? 0.0, wc_get_price_decimals() ),
            'logged_in'      => is_user_logged_in(),
        ];
    }

    /**
     * True when the block shows: action "show" and every rule matches, or action "hide" and not every rule does.
     *
     * @param array $setting    A normalize()d setting.
     * @param array $state      As state() returns it.
     * @param int   $step       The open step, 1-based.
     * @param int   $step_count The layout's steps; 0 without a Steps block, when no step rule matches.
     * @param array $empty_ids  The blockIds that are empty, as BlockEmptiness::empty_ids() returns them.
     */
    public static function is_visible( array $setting, array $state, int $step, int $step_count, array $empty_ids = [] ): bool {
        foreach ( $setting['rules'] as $rule ) {
            if ( ! self::matches( $rule, $state, $step, $step_count, $empty_ids ) ) {
                return 'show' !== $setting['action'];
            }
        }

        return 'show' === $setting['action'];
    }

    /**
     * Marks a conditional block with its setting for the script, and hides it when it fails with step 1
     * open; a setting with a "block" rule is left to finalize(). Anything else comes back untouched: no
     * layout, an excluded block, no valid setting, or no HTML.
     *
     * @param string $block_content Rendered block.
     * @param array  $block         Parsed block.
     */
    public function filter_block( $block_content, $block ): string {
        $block_content = (string) $block_content;

        if ( '' === $block_content || ! is_array( $block ) || in_array( $block['blockName'] ?? null, self::EXCLUDED, true ) ) {
            return $block_content;
        }

        $setting = self::normalize( $block['attrs'][ self::ATTRIBUTE ] ?? null );

        if ( null === $setting || null === CheckoutPage::get_instance()->layout() ) {
            return $block_content;
        }

        $html = new \WP_HTML_Tag_Processor( $block_content );

        if ( ! $html->next_tag() ) {
            return $block_content;
        }

        $html->set_attribute( self::DATA_ATTR, wp_json_encode( $setting ) );

        if ( [] !== self::block_ids( $setting ) ) {
            $this->deferred = true;
        } elseif ( ! self::is_visible( $setting, self::state(), 1, self::step_count() ) ) {
            $html->add_class( self::HIDDEN_CLASS );
        }

        $this->rendered = true;
        // The layout renders before wp_head(), so the script is enqueued in time.
        wp_enqueue_script( self::SCRIPT );

        return $html->get_updated_html();
    }

    /**
     * Decides the blocks filter_block() left for later, now that every block they point at has rendered,
     * with step 1 open. The checkout template passes it the whole rendered layout.
     *
     * @param string $html The rendered layout.
     */
    public function finalize( string $html ): string {
        if ( ! $this->deferred ) {
            return $html;
        }

        $settings = [];
        $targets  = [];
        $tags     = new \WP_HTML_Tag_Processor( $html );

        while ( $tags->next_tag() ) {
            $setting = self::setting_of( $tags->get_attribute( self::DATA_ATTR ) );

            if ( null !== $setting && [] !== self::block_ids( $setting ) ) {
                $settings[] = $setting;
                $targets    = array_merge( $targets, self::block_ids( $setting ) );
            }
        }

        if ( [] === $settings ) {
            return $html;
        }

        $empty_ids  = BlockEmptiness::empty_ids( $html, array_values( array_unique( $targets ) ) );
        $state      = self::state();
        $step_count = self::step_count();
        $tags       = new \WP_HTML_Tag_Processor( $html );

        while ( $tags->next_tag() ) {
            $setting = self::setting_of( $tags->get_attribute( self::DATA_ATTR ) );

            if ( null !== $setting && [] !== self::block_ids( $setting ) && ! self::is_visible( $setting, $state, 1, $step_count, $empty_ids ) ) {
                $tags->add_class( self::HIDDEN_CLASS );
            }
        }

        return $tags->get_updated_html();
    }

    /** Prints the state for the script, once a block was decorated. */
    public function print_state(): void {
        if ( $this->rendered ) {
            echo self::state_element(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG and JSON_HEX_AMP, so it can't close the element.
        }
    }

    /** Swaps the footer's state for the recalculated cart's, when the served layout holds a setting. */
    public function fragments( $fragments ) {
        $layout = CheckoutPage::get_instance()->layout();

        if ( ! is_array( $fragments ) || null === $layout || false === strpos( $layout->content, self::ATTRIBUTE ) ) {
            return $fragments;
        }

        $fragments[ '#' . self::STATE_ID ] = self::state_element();

        return $fragments;
    }

    /** The state() and the layout's step count as a JSON element. */
    private static function state_element(): string {
        return JsonElementHelper::html( self::STATE_ID, self::state() + [ 'step_count' => self::step_count() ] );
    }

    /**
     * The blockIds a setting's "block" rules point at.
     *
     * @param array $setting A normalize()d setting.
     * @return string[]
     */
    private static function block_ids( array $setting ): array {
        $ids = [];

        foreach ( $setting['rules'] as $rule ) {
            if ( 'block' === $rule['type'] ) {
                $ids[] = $rule['block'];
            }
        }

        return $ids;
    }

    /**
     * The setting a DATA_ATTR value holds, or null.
     *
     * @param mixed $value The attribute as WP_HTML_Tag_Processor returns it.
     */
    private static function setting_of( $value ): ?array {
        return is_string( $value ) ? self::normalize( json_decode( $value, true ) ) : null;
    }

    /** The steps in the served layout's first Steps block; 0 without one. Memoised by layout_titles(). */
    private static function step_count(): int {
        return count( LayoutSteps::layout_titles() );
    }

    /**
     * One rule with only its known keys, or null when its parameters are invalid.
     *
     * @param string $type A RULE_TYPES entry.
     * @param array  $rule The rule as parsed.
     */
    private static function normalize_rule( string $type, array $rule ): ?array {
        if ( 'step' === $type ) {
            $step = $rule['step'] ?? null;

            if ( 'last' === $step ) {
                return [
                    'type' => $type,
                    'step' => 'last',
                ];
            }

            // An integral float too, as JSON may write it and visibility-rules.ts accepts it.
            if ( ( ! is_int( $step ) && ! is_float( $step ) ) || floor( $step ) !== (float) $step || $step < 1 ) {
                return null;
            }

            return [
                'type' => $type,
                'step' => (int) $step,
            ];
        }

        if ( 'block' === $type ) {
            $block = $rule['block'] ?? null;
            $is    = $rule['is'] ?? null;

            if ( ! is_string( $block ) || ! preg_match( BlockEmptiness::BLOCK_ID_PATTERN, $block ) || ! in_array( $is, [ 'empty', 'not_empty' ], true ) ) {
                return null;
            }

            return [
                'type'  => $type,
                'block' => $block,
                'is'    => $is,
            ];
        }

        if ( 'subtotal' === $type ) {
            $op     = $rule['op'] ?? null;
            $amount = $rule['amount'] ?? null;

            if ( ! in_array( $op, [ 'gte', 'lt' ], true ) || ( ! is_int( $amount ) && ! is_float( $amount ) ) || ! is_finite( (float) $amount ) || $amount < 0 ) {
                return null;
            }

            return [
                'type'   => $type,
                'op'     => $op,
                'amount' => (float) $amount,
            ];
        }

        return [ 'type' => $type ];
    }

    /**
     * True when one rule holds for the state, the open step and the empty blocks.
     *
     * @param array    $rule  A normalize()d rule.
     * @param string[] $empty_ids The blockIds that are empty.
     */
    private static function matches( array $rule, array $state, int $step, int $step_count, array $empty_ids ): bool {
        switch ( $rule['type'] ) {
            case 'block':
                return in_array( $rule['block'], $empty_ids, true ) === ( 'empty' === $rule['is'] );
            case 'step':
                return $step_count > 0 && ( $rule['step'] === $step || ( 'last' === $rule['step'] && $step === $step_count ) );
            case 'subtotal':
                $subtotal = (float) ( $state['subtotal'] ?? 0.0 );

                return 'gte' === $rule['op'] ? $subtotal >= $rule['amount'] : $subtotal < $rule['amount'];
            default:
                return ! empty( $state[ $rule['type'] ] );
        }
    }
}
