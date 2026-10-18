<?php
namespace YayCheckout\Renderer;

use YayCheckout\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a form printed inside the Checkout block, such as a currency switcher's, from ending the
 * checkout form. Browsers drop a nested `<form>` but honour a `</form>`, which closes the checkout
 * form there and leaves every field after it out of the order. A matched pair becomes a `<div>` pair,
 * so its fields post with the checkout and the plugin's script still finds its markup; an unmatched
 * tag is dropped, as a browser drops a nested opener. It runs on the Checkout block's first paint
 * and on every fragment update_order_review swaps in. A form inside `<template>` is left alone: it is
 * parsed apart from the page, as the Login dialog's is.
 */
class CheckoutNestedForms {

    use SingletonTrait;

    protected function __construct() {
        // After CheckoutFragments and BlockVisibility (20), so their fragments are rewritten too.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's AJAX fragment filter.
        add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'fragments' ], 30 );
    }

    /**
     * On woocommerce_update_order_review_fragments: every fragment lands inside the checkout form.
     *
     * @param mixed $fragments Selector => HTML.
     * @return mixed
     */
    public function fragments( $fragments ) {
        if ( ! is_array( $fragments ) || null === CheckoutPage::get_instance()->layout() ) {
            return $fragments;
        }

        foreach ( $fragments as $selector => $html ) {
            if ( is_string( $html ) ) {
                $fragments[ $selector ] = self::neutralize( $html );
            }
        }

        return $fragments;
    }

    /** $html with each form pair outside `<template>` turned into a `<div>` pair, and each unmatched form tag dropped. */
    public static function neutralize( string $html ): string {
        // Not a bare 'form': every field row prints class="form-row". A form inside <template> still costs
        // a walk, since only the tokenizer tells template content from text that merely looks like it.
        if ( 1 !== preg_match( '#</?form\b#i', $html ) ) {
            return $html;
        }

        $tags     = self::tokenizer( $html );
        $template = 0;
        $openers  = [];
        $edits    = [];

        while ( $tags->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
            $name = $tags->get_tag();

            if ( 'TEMPLATE' === $name ) {
                $template = max( 0, $template + ( $tags->is_tag_closer() ? -1 : 1 ) );
                continue;
            }

            if ( 'FORM' !== $name || 0 !== $template ) {
                continue;
            }

            $span = $tags->token_span();

            // Fail closed: should the HTML API's internals change, leave the markup as it is.
            if ( null === $span || 0 !== stripos( substr( $html, $span[0], $span[1] ), $tags->is_tag_closer() ? '</form' : '<form' ) ) {
                return $html;
            }

            if ( ! $tags->is_tag_closer() ) {
                $openers[] = $span;
            } elseif ( [] === $openers ) {
                $edits[ $span[0] ] = [ $span[1], '' ];
            } else {
                // An opener keeps its attributes: only `<form` is replaced. A closer is replaced whole.
                $edits[ array_pop( $openers )[0] ] = [ strlen( '<form' ), '<div' ];
                $edits[ $span[0] ]                 = [ $span[1], '</div>' ];
            }
        }

        foreach ( $openers as $span ) {
            $edits[ $span[0] ] = [ $span[1], '' ];
        }

        // Keyed by offset, one edit per tag; applied from the end, so earlier offsets stay valid.
        krsort( $edits );

        foreach ( $edits as $start => [ $length, $text ] ) {
            $html = substr_replace( $html, $text, $start, $length );
        }

        return $html;
    }

    /**
     * WordPress's tag processor, which also says where the current tag sits in the HTML. That position is
     * in its protected bookmarks, so tags are found by the HTML API, never by matching text.
     */
    private static function tokenizer( string $html ): \WP_HTML_Tag_Processor {
        return new class( $html ) extends \WP_HTML_Tag_Processor {

            /** @return int[]|null The current tag's byte offset and length; null if the bookmark isn't the WP_HTML_Span of WordPress 6.5+. */
            public function token_span(): ?array {
                $this->set_bookmark( 'yaycheckout-tag' );
                $span = $this->bookmarks['yaycheckout-tag'] ?? null;

                return $span instanceof \WP_HTML_Span && is_int( $span->start ) && is_int( $span->length ) ? [ $span->start, $span->length ] : null;
            }
        };
    }
}
