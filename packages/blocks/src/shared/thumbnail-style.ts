/**
 * Thumbnail settings as --yaycheckout-thumbnail-* vars, for the blocks that show product images
 * (Order Summary Items, Order Details). Mirrors CheckoutFragments::thumbnail_style_vars(): the same
 * px ranges, ratios and var names.
 */

export type ThumbnailRatio = 'square' | 'portrait' | 'landscape' | 'original';

export interface ThumbnailAttributes {
	thumbnailRatio?: ThumbnailRatio;
	thumbnailSize?: number;
	thumbnailRadius?: number;
}

export const THUMBNAIL_SIZE = { min: 40, max: 96 };
export const THUMBNAIL_RADIUS = { min: 0, max: 48 };

// Square is the stylesheets' fallback. Original is the image's own ratio on the storefront, and square
// in the editor, where the placeholder has none.
const THUMBNAIL_RATIOS: Partial< Record< ThumbnailRatio, string > > = {
	portrait: '3 / 4',
	landscape: '4 / 3',
};

export function clampedPx( value: number, { min, max }: { min: number; max: number } ) {
	return `${ Math.max( min, Math.min( max, value ) ) }px`;
}

export function thumbnailStyleVars( {
	thumbnailRatio,
	thumbnailSize,
	thumbnailRadius,
}: ThumbnailAttributes ): Record< string, string > {
	const ratio = thumbnailRatio ? THUMBNAIL_RATIOS[ thumbnailRatio ] : undefined;

	return {
		...( typeof thumbnailSize === 'number'
			? { '--yaycheckout-thumbnail-size': clampedPx( thumbnailSize, THUMBNAIL_SIZE ) }
			: {} ),
		...( typeof thumbnailRadius === 'number'
			? { '--yaycheckout-thumbnail-radius': clampedPx( thumbnailRadius, THUMBNAIL_RADIUS ) }
			: {} ),
		...( ratio ? { '--yaycheckout-thumbnail-ratio': ratio } : {} ),
	};
}
