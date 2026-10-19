<?php
// wp eval-file seed.php [clean]
$clean = isset( $args[0] ) && 'clean' === $args[0];
$opts  = [ 'yaydp_product_pricing_rules', 'yaydp_cart_discount_rules', 'yaydp_checkout_fee_rules' ];
foreach ( get_posts( [ 'post_type' => 'product', 'post_status' => 'any', 's' => 'YPTEST', 'numberposts' => -1 ] ) as $p ) {
	wp_delete_post( $p->ID, true );
}
foreach ( $opts as $o ) { delete_option( $o ); }
if ( $clean ) { echo "cleaned\n"; return; }

$mk = function ( $name, $price ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name ); $p->set_regular_price( $price ); $p->set_status( 'publish' );
	$p->set_manage_stock( false ); $p->set_virtual( false );
	return $p->save();
};
$ids = [
	'simple' => $mk( 'YPTEST Simple', '100' ),
	'bulk'   => $mk( 'YPTEST Bulk', '50' ),
	'bxgy'   => $mk( 'YPTEST Buy X', '80' ),
	'giftA'  => $mk( 'YPTEST Gift A', '30' ),
	'giftB'  => $mk( 'YPTEST Gift B', '40' ),
	'bogo'   => $mk( 'YPTEST BOGO', '25' ),
	'plain'  => $mk( 'YPTEST Plain', '60' ),
];
$f = function ( $id, $q = 1 ) { return [ 'quantity' => $q, 'type' => 'product', 'comparation' => 'in_list', 'value' => [ [ 'value' => $id, 'label' => get_the_title( $id ) ] ] ]; };
$base = function ( $n, $type, $extra ) {
	return array_merge( [
		'id' => 'yptest-' . $n, 'rule_id' => 'yptest' . $n, 'name' => "YPTEST $type $n", 'type' => $type, 'is_enabled' => true,
		'conditions' => [ 'match_type' => 'any', 'logics' => [] ], 'schedule' => [], 'maximum_uses' => [ 'enable' => false ],
	], $extra );
};
$pp = [
	$base( 1, 'simple_adjustment', [ 'buy_products' => [ 'match_type' => 'any', 'filters' => [ $f( $ids['simple'] ) ] ],
		'pricing' => [ 'type' => 'percentage_discount', 'value' => 20, 'maximum_value' => null ] ] ),
	$base( 2, 'bulk_pricing', [ 'buy_products' => [ 'match_type' => 'any', 'filters' => [ $f( $ids['bulk'] ) ] ],
		'pricing_ranges' => [
			[ 'from_quantity' => 2, 'to_quantity' => 4, 'pricing' => [ 'type' => 'percentage_discount', 'value' => 10, 'maximum_value' => null ] ],
			[ 'from_quantity' => 5, 'to_quantity' => '', 'pricing' => [ 'type' => 'percentage_discount', 'value' => 20, 'maximum_value' => null ] ],
		], 'pricing' => [ 'type' => 'percentage_discount', 'value' => 0, 'maximum_value' => null ] ] ),
	$base( 3, 'buy_x_get_y', [ 'buy_products' => [ 'match_type' => 'any', 'filters' => [ $f( $ids['bxgy'] ) ] ],
		'get_products' => [ 'match_type' => 'any', 'filters' => [ $f( $ids['giftA'] ), $f( $ids['giftB'] ) ] ],
		'pricing' => [ 'type' => 'percentage_discount', 'value' => 100, 'maximum_value' => null, 'buy_quantity' => 1, 'get_quantity' => 1, 'item_get_type' => 'free', 'repeat' => false, 'received_item_type' => 'default' ] ] ),
	$base( 4, 'bogo', [ 'buy_products' => [ 'match_type' => 'any', 'filters' => [ $f( $ids['bogo'] ) ] ],
		'pricing' => [ 'type' => 'percentage_discount', 'value' => 100, 'maximum_value' => null, 'buy_quantity' => 2, 'get_quantity' => 1, 'item_get_type' => 'free', 'repeat' => false ] ] ),
];
update_option( 'yaydp_product_pricing_rules', $pp );
update_option( 'yaydp_cart_discount_rules', [
	$base( 7, 'cart_discount', [ 'pricing' => [ 'type' => 'fixed_discount', 'value' => 7, 'maximum_value' => null ],
		'conditions' => [ 'match_type' => 'any', 'logics' => [ [ 'type' => 'payment_method', 'comparation' => 'in_list', 'value' => [ [ 'value' => 'cod', 'label' => 'COD' ] ] ] ] ] ] ),
	$base( 5, 'cart_discount', [ 'pricing' => [ 'type' => 'percentage_discount', 'value' => 10, 'maximum_value' => null ],
		'conditions' => [ 'match_type' => 'any', 'logics' => [ [ 'type' => 'cart_subtotal_price', 'comparation' => 'gte', 'value' => 100 ] ] ] ] ),
] );
update_option( 'yaydp_checkout_fee_rules', [
	$base( 8, 'shipping_fee', [ 'pricing' => [ 'type' => 'percentage_discount', 'value' => 50, 'maximum_value' => null ] ] ),
	$base( 9, 'custom_shipping_fee', [ 'pricing' => [ 'type' => 'fixed_fee', 'value' => 2, 'maximum_value' => null ], 'tax' => 'standard',
		'conditions' => [ 'match_type' => 'any', 'logics' => [ [ 'type' => 'payment_method', 'comparation' => 'in_list', 'value' => [ [ 'value' => 'bacs', 'label' => 'BACS' ] ] ] ] ] ] ),
	$base( 6, 'custom_fee', [ 'pricing' => [ 'type' => 'fixed_fee', 'value' => 5, 'maximum_value' => null ], 'tax' => 'standard' ] ),
] );
echo wp_json_encode( $ids ), "\n";
