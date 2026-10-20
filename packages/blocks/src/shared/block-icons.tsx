/**
 * Inserter and list-view icons, one export per block so each block's bundle carries only its own.
 * One rounded-outline family: 1.5 stroke, round caps and joins, soft corners.
 * Strokes use currentColor so the editor tints them. The inline fill beats the
 * editor's `.block-editor-block-icon svg { fill: currentColor }`, which would fill every shape.
 */
const icon = ( children: JSX.Element ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		fill="none"
		stroke="currentColor"
		strokeWidth={ 1.5 }
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
		focusable="false"
		style={ { fill: 'none' } }
	>
		{ children }
	</svg>
);

export const stepsIcon = /*#__PURE__*/ icon(
	<>
		<rect x="4" y="4" width="16" height="4" rx="1.5" />
		<rect x="4" y="10" width="16" height="4" rx="1.5" />
		<rect x="4" y="16" width="16" height="4" rx="1.5" />
	</>
);
export const stepsProgressIcon = /*#__PURE__*/ icon(
	<>
		<circle cx="5" cy="12" r="2" />
		<circle cx="12" cy="12" r="2" />
		<circle cx="19" cy="12" r="2" />
		<path d="M7 12h3M14 12h3" />
	</>
);
export const stepIcon = /*#__PURE__*/ icon(
	<>
		<circle cx="7" cy="12" r="3" />
		<path d="M13 12h7" />
	</>
);
export const stepButtonIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="7" width="18" height="10" rx="5" />
		<path d="m12.5 10 2 2-2 2M8 12h6.5" />
	</>
);
export const backButtonIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="7" width="18" height="10" rx="5" />
		<path d="m11.5 10-2 2 2 2M16 12H9.5" />
	</>
);
export const returnToCartIcon = /*#__PURE__*/ icon(
	<>
		<path d="M3 4.5h2.5l2 10h11l1.75-7H6" />
		<circle cx="9" cy="18.5" r="1.25" />
		<circle cx="17" cy="18.5" r="1.25" />
		<path d="m12.5 9-2 2 2 2M10.5 11h5" />
	</>
);
export const goToStepIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="7" width="18" height="10" rx="5" />
		<path d="M9 10.5h5a2 2 0 0 1 0 4H9m2-6-2 2 2 2" />
	</>
);
// The Yay cube, from the brand logo.
export const checkoutIcon = /*#__PURE__*/ icon( <path d="M12 3.5 19.5 7.75v8.5L12 20.5l-7.5-4.25v-8.5L12 3.5ZM4.5 7.75 12 12l7.5-4.25M12 12v8.5" /> );
export const loginIcon = /*#__PURE__*/ icon(
	<>
		<path d="M14 4.5h3.5A2.5 2.5 0 0 1 20 7v10a2.5 2.5 0 0 1-2.5 2.5H14" />
		<path d="m10 8 4 4-4 4M14 12H4" />
	</>
);
export const emailIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="5" width="18" height="14" rx="3" />
		<path d="m6 8.5 6 4.5 6-4.5" />
	</>
);
export const createAccountIcon = /*#__PURE__*/ icon(
	<>
		<circle cx="9" cy="8" r="3" />
		<path d="M4 18.5c0-2.75 2.25-5 5-5s5 2.25 5 5" />
		<path d="M17 8v6M14 11h6" />
	</>
);
export const billingIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="5" width="18" height="14" rx="3" />
		<circle cx="8.5" cy="10.5" r="1.75" />
		<path d="M6 15.5a2.5 2.5 0 0 1 5 0M14 10h4M14 14h4" />
	</>
);
export const shippingIcon = /*#__PURE__*/ icon(
	<>
		<path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11Z" />
		<circle cx="12" cy="10" r="2" />
	</>
);
export const differentAddressIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="4" width="7" height="7" rx="2" />
		<path d="m5 7.5 1.25 1.25L8.5 6.5M14 7.5h7M3 16h18M17 12.5 20.5 16 17 19.5" />
	</>
);
export const fieldIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="7" width="18" height="10" rx="3" />
		<path d="M7.5 10v4" />
	</>
);
export const extraFieldIcon = /*#__PURE__*/ icon(
	<>
		<path d="M14 17H6a3 3 0 0 1-3-3v-4a3 3 0 0 1 3-3h8" />
		<path d="M7.5 10v4M19 9v6M16 12h6" />
	</>
);
export const orderNoteIcon = /*#__PURE__*/ icon(
	<>
		<path d="M14 3.5H7.5a2.5 2.5 0 0 0-2.5 2.5v12a2.5 2.5 0 0 0 2.5 2.5h9a2.5 2.5 0 0 0 2.5-2.5V8.5l-5-5Z" />
		<path d="M14 3.5v5h5M8.5 12.5h7M8.5 16h5" />
	</>
);
export const additionalFieldsIcon = /*#__PURE__*/ icon(
	<>
		<path d="M12 20.5H7.5A2.5 2.5 0 0 1 5 18V6a2.5 2.5 0 0 1 2.5-2.5H14l5 5V12" />
		<path d="M14 3.5v5h5M8.5 12.5h5M8.5 16h2.5M18 15v6M15 18h6" />
	</>
);
export const shippingMethodsIcon = /*#__PURE__*/ icon(
	<>
		<path d="M14 16V7a1.5 1.5 0 0 0-1.5-1.5h-8A1.5 1.5 0 0 0 3 7v7.5A1.5 1.5 0 0 0 4.5 16H5" />
		<path d="M14 9h3.5l3.5 4v1.5a1.5 1.5 0 0 1-1.5 1.5H19M9 16h6" />
		<circle cx="7" cy="17" r="2" />
		<circle cx="17" cy="17" r="2" />
	</>
);
export const expressPayIcon = /*#__PURE__*/ icon( <path d="M13 3 5 13.5h6.5L11 21l8-10.5h-6.5L13 3Z" /> );
export const paymentIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="5" width="18" height="14" rx="3" />
		<path d="M3 10h18M7 15h3" />
	</>
);
export const couponFormIcon = /*#__PURE__*/ icon(
	<>
		<path d="M3 9V8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a3 3 0 0 0 0 6v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a3 3 0 0 0 0-6Z" />
		<path d="M10 8.5v7" strokeDasharray="1.5 2" />
	</>
);
export const couponButtonIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="7" width="18" height="10" rx="3" />
		<path d="M8 12h8" />
	</>
);
export const orderSummaryIcon = /*#__PURE__*/ icon(
	<>
		<path d="M5 5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v15.5l-2.33-1.5-2.34 1.5L12 19l-2.33 1.5L7.33 19 5 20.5V5Z" />
		<path d="M9 8h6M9 12h6" />
	</>
);
export const orderSummaryHeaderIcon = /*#__PURE__*/ icon( <path d="M4 6h16v5H4zM15 9l1.5 1.5L18 9" /> );
export const orderSummaryDetailsIcon = /*#__PURE__*/ icon( <path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5" /> );
export const orderDetailsIcon = /*#__PURE__*/ icon( <path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5" /> );
export const orderSummaryToggleIcon = /*#__PURE__*/ icon( <path d="M4 7h16v10H4zM9 11l3 3 3-3" /> );
export const orderSummaryItemsIcon = /*#__PURE__*/ icon( <path d="M4 6h16M4 12h16M4 18h10" /> );
export const orderSummaryBreakdownIcon = /*#__PURE__*/ icon( <path d="M4 7h16M4 12h16M12 17h8" /> );
export const orderSummaryTotalIcon = /*#__PURE__*/ icon( <path d="M4 9h16M4 15h16" /> );
export const savingsNoteIcon = /*#__PURE__*/ icon(
	<>
		<path d="M12 3v18M8 8h5.5a2.5 2.5 0 0 1 0 5H9a2.5 2.5 0 0 0 0 5h6" />
	</>
);
export const fieldValueIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="5" width="18" height="14" rx="2" />
		<path d="M7 10h4M7 14h10" />
	</>
);
export const termsIcon = /*#__PURE__*/ icon(
	<>
		<path d="M6 3h9l3 3v15H6z" />
		<path d="M9 9h6M9 13h6M9 17h3" />
	</>
);
export const placeOrderIcon = /*#__PURE__*/ icon(
	<>
		<rect x="3" y="7" width="18" height="10" rx="5" />
		<path d="M8 12.25 10.5 14.5 16 9.5" />
	</>
);
export const slotIcon = /*#__PURE__*/ icon( <rect x="4" y="4" width="16" height="16" rx="3" strokeDasharray="3 2.5" /> );
export const wcHookIcon = /*#__PURE__*/ icon(
	<>
		<path d="M12 3v9a4 4 0 1 1-4-4" />
		<path d="M10 6l2-3 2 3" />
	</>
);
