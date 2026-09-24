/**
 * Inserter/list-view icon per block name; unlisted blocks keep the default.
 *
 * 24px grid, filled paths, no colour: the editor tints via currentColor.
 */
const icon = ( ...paths: string[] ) => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		{ paths.map( ( d ) => (
			<path key={ d } d={ d } fillRule="evenodd" clipRule="evenodd" />
		) ) }
	</svg>
);

export const blockIcons: Record< string, JSX.Element > = {
	'yaycheckout/checkout': icon(
		'M8 7V6a4 4 0 0 1 8 0v1h3v13H5V7h3Zm1.5 0h5V6a2.5 2.5 0 0 0-5 0v1ZM6.5 8.5v10h11v-10h-11Z',
		'M15.44 11.44 16.5 12.5l-4.97 4.97-3.03-3.03 1.06-1.06 1.97 1.97 3.91-3.91Z'
	),
	'yaycheckout/contact': icon(
		'M3 5h18v14H3V5Zm2.8 1.5L12 11l6.2-4.5H5.8Zm-1.3.8v10.2h15V7.3L12 12.8 4.5 7.3Z'
	),
	'yaycheckout/billing': icon(
		'M3 5h18v14H3V5Zm1.5 1.5v11h15v-11h-15Z',
		'M8.5 8.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z',
		'M5.5 16a3 3 0 0 1 6 0h-6Z',
		'M13 9.5h5V11h-5V9.5Zm0 3.5h5v1.5h-5V13Z'
	),
	'yaycheckout/shipping': icon(
		'M12 3a6 6 0 0 1 6 6c0 4.5-6 12-6 12S6 13.5 6 9a6 6 0 0 1 6-6Zm0 4a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z'
	),
	'yaycheckout/field': icon(
		'M3 7h18v10H3V7Zm1.5 1.5v7h15v-7h-15Z',
		'M7 10h1.5v4H7v-4Z'
	),
	'yaycheckout/extra-field': icon(
		'M3 7h12v1.5H4.5v7H15V17H3V7Z',
		'M7 10h1.5v4H7v-4Z',
		'M18 8.5h1.5V11H22v1.5h-2.5V15H18v-2.5h-2.5V11H18V8.5Z'
	),
	'yaycheckout/order-review': icon(
		'm4 7.2 1.06-1.06L6.5 7.58l2.44-2.44L10 6.2 6.5 9.7 4 7.2Z',
		'M12 7h8v1.5h-8V7Z',
		'm4 15.2 1.06-1.06 1.44 1.44 2.44-2.44L10 14.2l-3.5 3.5L4 15.2Z',
		'M12 15h8v1.5h-8V15Z'
	),
	'yaycheckout/shipping-methods': icon(
		'M2 6h12v9H2V6Z',
		'M15 9h3.5l3 4v2H15V9Z',
		'M7 15.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm10.5 0a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z'
	),
	'yaycheckout/express-pay': icon( 'M13 2 4 14h7l-1 8 9-12h-7l1-8Z' ),
	'yaycheckout/payment': icon(
		'M3 5h18v14H3V5Zm1.5 1.5v2h15v-2h-15Zm0 4.5v6.5h15V11h-15Z',
		'M6 14h4v1.5H6V14Z'
	),
	'yaycheckout/order-summary': icon(
		'M5 3h14v18l-2.33-1.5L14.33 21 12 19.5 9.67 21l-2.34-1.5L5 21V3Zm1.5 1.5v13.7l.83-.53 2.34 1.5 2.33-1.5 2.33 1.5 2.34-1.5.83.53V4.5h-11Z',
		'M8.5 8h7v1.5h-7V8Zm0 3.5h7V13h-7v-1.5Z'
	),
	'yaycheckout/place-order': icon(
		'M3 8h18v8H3V8Zm6 3.25v1.5h3.2l-1.47 1.47 1.06 1.06L15.06 12l-3.27-3.28-1.06 1.06 1.47 1.47H9Z'
	),
	'yaycheckout/slot': icon(
		'M4 4h3v1.5H5.5V7H4V4Zm13 0h3v3h-1.5V5.5H17V4ZM4 17h1.5v1.5H7V20H4v-3Zm14.5 0H20v3h-3v-1.5h1.5V17ZM10.5 4h3v1.5h-3V4Zm0 14.5h3V20h-3v-1.5ZM4 10.5h1.5v3H4v-3Zm14.5 0H20v3h-1.5v-3Z'
	),
};
