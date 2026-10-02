import type { ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import { countDirtyLeaves } from '@/lib/helpers/form.helper';
import { FONT_FAMILIES, type DesignForm, type DesignTokens, type TokenSection } from '@/lib/schema/design.schema';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { FormField } from '@/components/ui/form';
import { Switch } from '@/components/ui/switch';
import { BUTTON_STYLE_LABELS, ButtonsGroup } from './ButtonsGroup';
import { ColoursGroup } from './ColoursGroup';
import { CustomCssGroup } from './CustomCssGroup';
import { INPUT_SIZE_LABELS, INPUT_STYLE_LABELS, InputsGroup } from './InputsGroup';
import { SpacingGroup } from './SpacingGroup';
import { TypographyGroup } from './TypographyGroup';

/** Each group header restates its current values so a collapsed list still reads as a design. */
export const GROUPS: {
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
      `${FONT_FAMILIES[typography.fontFamily] || __('Theme default', 'yaycheckout')} · ${typography.baseSize}px`,
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
    summary: ({ buttons }) => `${BUTTON_STYLE_LABELS[buttons.style]} · ${buttons.radius}px`,
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
 * The token editor: one collapsible group per section, Colours open by default.
 * Each header restates the group's values, or "Theme default" while its switch
 * is off, and counts its edits since the last save — the same measure as the
 * "changed" row tags.
 */
export function TokenGroups() {
  // Read during render so react-hook-form's proxy subscribes to dirtyFields.
  const { control, formState } = useFormContext<DesignForm>();
  const { dirtyFields } = formState;
  const tokens = useWatch({ control, name: 'tokens' });
  const customCss = useWatch({ control, name: 'customCss' });

  return (
    <Accordion type="multiple" defaultValue={['colours']} className="flex flex-col gap-3">
      {GROUPS.map(({ id, label, body: Body, summary }) => {
        const changed = countDirtyLeaves('customCss' === id ? dirtyFields.customCss : dirtyFields.tokens?.[id]);
        const off = 'customCss' !== id && !tokens[id].enabled;

        return (
          <AccordionItem key={id} value={id}>
            {/* The switch sits beside the trigger, not in it: a button inside a button is invalid HTML. */}
            <div className="flex items-center [&>h3]:flex-1">
              <AccordionTrigger>
                <span className="flex-1">{label}</span>
                <span className="text-muted-foreground text-xs font-normal">
                  {off ? __('Theme default', 'yaycheckout') : summary(tokens, customCss)}
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
              {'customCss' !== id ? (
                <FormField
                  control={control}
                  name={`tokens.${id}.enabled`}
                  render={({ field }) => (
                    <Switch
                      className="mr-4"
                      checked={field.value}
                      onCheckedChange={field.onChange}
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
              {off ? (
                <p className="text-muted-foreground p-4 text-sm">
                  {__("Uses your theme's styles. Turn on to customize.", 'yaycheckout')}
                </p>
              ) : (
                <Body />
              )}
            </AccordionContent>
          </AccordionItem>
        );
      })}
    </Accordion>
  );
}
