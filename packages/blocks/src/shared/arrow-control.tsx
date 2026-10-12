import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { arrowOptions, type Arrow, type ArrowDirection } from './button-arrow';

interface Props {
	direction: ArrowDirection;
	value: Arrow;
	onChange: ( arrow: Arrow ) => void;
}

/** The "Icon" select of Continue, Back and Return to Cart. Its values are the blocks' block.json `arrow` enum. */
export function ArrowControl( { direction, value, onChange }: Props ) {
	return (
		<SelectControl
			label={ __( 'Icon', 'yaycheckout' ) }
			value={ value }
			options={ arrowOptions( direction ) }
			onChange={ ( next: string ) => onChange( next as Arrow ) }
		/>
	);
}
