import { useState, type ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import { isGroupOn } from '@/lib/helpers/design.helper';
import { FONT_FAMILIES, type DesignForm, type DesignTokens, type TokenSection } from '@/lib/schema/design.schema';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { FormField } from '@/components/ui/form';
import { Switch } from '@/components/ui/switch';
import { COLOUR_KEYS, ColoursGroup } from './ColoursGroup';
import { CustomCssGroup } from './CustomCssGroup';
import { INPUT_SIZE_LABELS, INPUT_STYLE_LABELS, InputsGroup } from './InputsGroup';
import { TypographyGroup } from './TypographyGroup';

/** Each group header restates its current values so a collapsed list still reads as a design; colours as swatches. */
export const GROUPS: {
  id: TokenSection | 'customCss';
  label: string;
  body: () => ReactNode;
  summary: (tokens: DesignTokens, customCss: string) => ReactNode;
}[] = [
  {
    id: 'colours',
    label: __('Colours', 'yaycheckout'),
    body: ColoursGroup,
    summary: ({ colours }) => (
      <span className="flex gap-1">
        {COLOUR_KEYS.map((colour) => (
          <span key={colour} className="size-3 rounded-full border" style={{ background: colours[colour] }} />
        ))}
      </span>
    ),
  },
  {
    id: 'typography',
    label: __('Typography', 'yaycheckout'),
    body: TypographyGroup,
    summary: ({ typography }) =>
      `${FONT_FAMILIES[typography.fontFamily] || __('Theme default', 'yaycheckout')} · ${typography.baseSize}px`,
  },
  {
    id: 'inputs',
    label: __('Inputs', 'yaycheckout'),
    body: InputsGroup,
    summary: ({ inputs }) =>
      `${INPUT_STYLE_LABELS[inputs.style]} · ${INPUT_SIZE_LABELS[inputs.size]} · ${inputs.radius}px`,
  },
  {
    id: 'customCss',
    label: __('Custom CSS', 'yaycheckout'),
    body: CustomCssGroup,
    summary: (_tokens, customCss) => {
      const lines = customCss.trim() ? customCss.trim().split('\n').length : 0;
      /* translators: %d: number of lines of custom CSS */
      return lines ? sprintf(_n('%d line', '%d lines', lines, 'yaycheckout'), lines) : __('empty', 'yaycheckout');
    },
  },
];

/**
 * One collapsible group per token section. A group's switch turns it on (open) or off
 * (closed and locked); Custom CSS has no switch and starts folded.
 */
export function TokenGroups() {
  const { control } = useFormContext<DesignForm>();
  const tokens = useWatch({ control, name: 'tokens' });
  const customCss = useWatch({ control, name: 'customCss' });
  // Groups folded by hand; every other on group is open, so a group a Reset or reload turns on opens too.
  const [folded, setFolded] = useState<string[]>(['customCss']);
  const openIds = GROUPS.map(({ id }) => id).filter((id) => isGroupOn(tokens, id) && !folded.includes(id));

  return (
    <Accordion
      type="multiple"
      value={openIds}
      onValueChange={(open) =>
        setFolded(GROUPS.map(({ id }) => id).filter((id) => isGroupOn(tokens, id) && !open.includes(id)))
      }
      className="flex flex-col gap-3"
    >
      {GROUPS.map(({ id, label, body: Body, summary }) => (
        <AccordionItem key={id} value={id} disabled={!isGroupOn(tokens, id)}>
          {/* The switch sits beside the trigger, not in it: a button inside a button is invalid HTML. */}
          <div className="flex items-center [&>h3]:flex-1">
            <AccordionTrigger className="disabled:cursor-default disabled:opacity-100 [&>svg]:hidden">
              <span className="flex-1">{label}</span>
              <span className="text-muted-foreground text-xs font-normal">
                {isGroupOn(tokens, id) ? summary(tokens, customCss) : __('Theme default', 'yaycheckout')}
              </span>
            </AccordionTrigger>
            {'customCss' !== id ? (
              <FormField
                control={control}
                name={`tokens.${id}.enabled`}
                render={({ field }) => (
                  <Switch
                    className="mr-4"
                    checked={field.value}
                    onCheckedChange={(checked) => {
                      field.onChange(checked);
                      setFolded(folded.filter((foldedId) => foldedId !== id));
                    }}
                    aria-label={sprintf(
                      /* translators: %s: design group name, e.g. Colours */
                      __('Customize %s', 'yaycheckout'),
                      label,
                    )}
                  />
                )}
              />
            ) : null}
          </div>
          <AccordionContent>
            <Body />
          </AccordionContent>
        </AccordionItem>
      ))}
    </Accordion>
  );
}
