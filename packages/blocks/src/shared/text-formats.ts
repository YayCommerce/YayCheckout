import { LIVE_VALUE_FORMAT } from '../live-value/constants';

/**
 * The rich-text toolbar of display text: a step title, a checkbox label, Field Value's empty text.
 * Bold, Italic, Highlight, Strikethrough, Subscript and Superscript. Buttons and field labels stay plain.
 * Leave out withoutInteractiveFormatting where Live value is offered: it drops interactive formats, Live value
 * among them. No link format is listed, so nothing interactive reaches the storefront.
 * LayoutMarkupReader::RICH_TEXT_TAGS must allow the tags these save, or render.php strips them.
 */
export const TEXT_FORMATS = [
	'core/bold',
	'core/italic',
	'core/text-color',
	'core/strikethrough',
	'core/subscript',
	'core/superscript',
];

/** TEXT_FORMATS and Live value, for blocks named in LIVE_VALUE_BLOCKS. */
export const TEXT_FORMATS_WITH_LIVE_VALUE = [ ...TEXT_FORMATS, LIVE_VALUE_FORMAT ];
