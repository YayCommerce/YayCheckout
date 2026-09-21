import type { ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import { countDirtyLeaves } from '@/lib/helpers/form.helper';
import { useDesignQuery } from '@/lib/queries/design.queries';
import { FONT_FAMILIES, type DesignForm, type DesignTokens, type TokenSection } from '@/lib/schema/design.schema';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { ButtonsGroup } from './ButtonsGroup';
import { ColoursGroup } from './ColoursGroup';
import { CustomCssGroup } from './CustomCssGroup';
import { InputsGroup } from './InputsGroup';
import { SpacingGroup } from './SpacingGroup';
import { TypographyGroup } from './TypographyGroup';

const STYLE_LABELS: Record<
  DesignTokens['buttons']['style'] | DesignTokens['inputs']['style'] | DesignTokens['inputs']['size'],
  string
> = {
  fill: __('Fill', 'yaycheckout'),
  outline: __('Outline', 'yaycheckout'),
  boxed: __('Boxed', 'yaycheckout'),
  underline: __('Underline', 'yaycheckout'),
  small: __('Small', 'yaycheckout'),
  medium: __('Medium', 'yaycheckout'),
  large: __('Large', 'yaycheckout'),
};

/** Each group header restates its current values so a collapsed list still reads as a design. */
const GROUPS: {
  id: TokenSection | 'customCss';
  label: string;
  body: () => ReactNode;
  summary: (tokens: DesignTokens, customCss: string) => string;
}[] = [
  {
    id: 'colours',
    label: __('Colours', 'yaycheckout'),
    body: ColoursGroup,
    summary: ({ colours }) => `${colours.primary} · ${colours.text}`,
  },
  {
    id: 'typography',
    label: __('Typography', 'yaycheckout'),
    body: TypographyGroup,
    summary: ({ typography }) =>
      typography.inherit
        ? __('Inherited', 'yaycheckout')
        : `${FONT_FAMILIES[typography.fontFamily] || __('Theme default', 'yaycheckout')} · ${typography.baseSize}px`,
  },
  {
    id: 'spacing',
    label: __('Spacing & radius', 'yaycheckout'),
    body: SpacingGroup,
    summary: ({ spacing }) => `${spacing.scale.toFixed(1)}× · ${spacing.radius}px`,
  },
  {
    id: 'buttons',
    label: __('Buttons', 'yaycheckout'),
    body: ButtonsGroup,
    summary: ({ buttons }) => `${STYLE_LABELS[buttons.style]} · ${buttons.radius}px`,
  },
  {
    id: 'inputs',
    label: __('Inputs', 'yaycheckout'),
    body: InputsGroup,
    summary: ({ inputs }) => `${STYLE_LABELS[inputs.style]} · ${STYLE_LABELS[inputs.size]} · ${inputs.radius}px`,
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
 * The token editor: one collapsible group per section, Colours open by default.
 * Each header restates the group's values, tagged "(from theme)" while every
 * token in it is still the Default, and counts its edits since the last save —
 * the same measure as the "changed" row tags.
 */
export function TokenGroups() {
  // Read during render so react-hook-form's proxy subscribes to dirtyFields.
  const { control, formState } = useFormContext<DesignForm>();
  const { dirtyFields } = formState;
  const { data } = useDesignQuery();
  const tokens = useWatch({ control, name: 'tokens' });
  const customCss = useWatch({ control, name: 'customCss' });

  return (
    <Accordion type="multiple" defaultValue={['colours']} className="flex flex-col gap-3">
      {GROUPS.map(({ id, label, body: Body, summary }) => {
        const changed = countDirtyLeaves('customCss' === id ? dirtyFields.customCss : dirtyFields.tokens?.[id]);
        const fromTheme =
          'customCss' !== id &&
          Object.entries(tokens[id]).every(([key, value]) => value === data.defaultTokens[id][key as never]);

        return (
          <AccordionItem key={id} value={id}>
            <AccordionTrigger>
              <span className="flex-1">{label}</span>
              <span className="text-muted-foreground text-xs font-normal">
                {summary(tokens, customCss)}
                {fromTheme ? ` ${__('(from theme)', 'yaycheckout')}` : null}
              </span>
              {changed > 0 ? (
                <Badge variant="outline" className="text-xs">
                  {sprintf(
                    /* translators: %d: number of tokens in this group edited since the last save */
                    __('%d changed', 'yaycheckout'),
                    changed,
                  )}
                </Badge>
              ) : null}
            </AccordionTrigger>
            <AccordionContent>
              <Body />
            </AccordionContent>
          </AccordionItem>
        );
      })}
    </Accordion>
  );
}
