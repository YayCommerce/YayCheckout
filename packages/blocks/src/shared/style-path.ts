/** Reading and writing nested paths of a block's `style` attribute, for the Styles tab panels. */

export type StyleObject = Record< string, unknown >;

export function isPlainObject( value: unknown ): value is StyleObject {
	return typeof value === 'object' && value !== null && ! Array.isArray( value );
}

/** True when a stored setting is set: neither missing nor empty. */
export function hasValue( value: unknown ): boolean {
	return value !== undefined && value !== null && value !== '';
}

export function getAt( style: StyleObject | undefined, path: string[] ): unknown {
	let cursor: unknown = style;

	for ( const key of path ) {
		if ( ! isPlainObject( cursor ) ) {
			return undefined;
		}
		cursor = cursor[ key ];
	}

	return cursor;
}

/** Set or clear one path. Drop empty nested objects so Copy/Paste styles stays tidy. */
export function setAt( style: StyleObject | undefined, path: string[], value: unknown ): StyleObject | undefined {
	if ( path.length === 0 ) {
		return style;
	}

	const [ head, ...rest ] = path;
	const current = isPlainObject( style ) ? { ...style } : {};

	if ( rest.length === 0 ) {
		if ( ! hasValue( value ) ) {
			delete current[ head ];
		} else {
			current[ head ] = value;
		}
	} else {
		const nested = setAt( isPlainObject( current[ head ] ) ? ( current[ head ] as StyleObject ) : undefined, rest, value );
		if ( nested === undefined || Object.keys( nested ).length === 0 ) {
			delete current[ head ];
		} else {
			current[ head ] = nested;
		}
	}

	return Object.keys( current ).length === 0 ? undefined : current;
}
