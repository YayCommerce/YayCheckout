import { __ } from '@wordpress/i18n';

import type { DesignTokens } from '@/lib/schema/design.schema';
import {
  ColorPicker,
  ColorPickerContent,
  ColorPickerInput,
  ColorPickerPanel,
  ColorPickerTrigger,
} from '@/components/ui/color-picker';
import { TokenField } from './TokenField';

const COLOUR_LABELS: Record<keyof DesignTokens['colours'], string> = {
  primary: __('Primary', 'yaycheckout'),
  text: __('Text', 'yaycheckout'),
  background: __('Background', 'yaycheckout'),
  border: __('Border', 'yaycheckout'),
};

export function ColoursGroup() {
  return (
    <>
      {(Object.keys(COLOUR_LABELS) as (keyof DesignTokens['colours'])[]).map((colour) => (
        <TokenField key={colour} section="colours" token={colour} label={COLOUR_LABELS[colour]}>
          {(field) => (
            <ColorPicker value={field.value} onValueChange={field.onChange}>
              <ColorPickerTrigger className="h-8 text-xs" />
              <ColorPickerContent>
                <ColorPickerPanel />
                <ColorPickerInput />
              </ColorPickerContent>
            </ColorPicker>
          )}
        </TokenField>
      ))}
    </>
  );
}
