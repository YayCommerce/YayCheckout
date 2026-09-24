import { __ } from '@wordpress/i18n';

export interface CheckoutHookOption {
	name: string;
	title: string;
	hook: string;
}

/** Selectable checkout actions. Keep in sync with YayCheckout\Helpers\HookBlockRunHelper::INSIDE_FORM. */
export const checkoutHooks: CheckoutHookOption[] = [
	{
		name: 'before-customer-details',
		title: __( 'Before customer details', 'yaycheckout' ),
		hook: 'woocommerce_checkout_before_customer_details',
	},
	{
		name: 'after-customer-details',
		title: __( 'After customer details', 'yaycheckout' ),
		hook: 'woocommerce_checkout_after_customer_details',
	},
	{
		name: 'before-billing-form',
		title: __( 'Before billing form', 'yaycheckout' ),
		hook: 'woocommerce_before_checkout_billing_form',
	},
	{
		name: 'after-billing-form',
		title: __( 'After billing form', 'yaycheckout' ),
		hook: 'woocommerce_after_checkout_billing_form',
	},
	{
		name: 'before-shipping-form',
		title: __( 'Before shipping form', 'yaycheckout' ),
		hook: 'woocommerce_before_checkout_shipping_form',
	},
	{
		name: 'after-shipping-form',
		title: __( 'After shipping form', 'yaycheckout' ),
		hook: 'woocommerce_after_checkout_shipping_form',
	},
];
