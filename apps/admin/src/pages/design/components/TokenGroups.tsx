import { useState, type ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { __, _n, sprintf } from '@wordpress/i18n';

import { countTokenDiffs } from '@/lib/helpers/design.helper';
import { useDesignQuery } from '@/lib/queries/design.queries';
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
 * The token editor: one collapsible group per section. The switch turns a group
 * on (opening it) or off (closing it, header locked); the header only folds an
 * on group away and back. Custom CSS has no switch and starts closed. Each
 * header restates the group's values, or "Theme default" while off, and while
 * on counts how it differs from the theme default (the Default preset), being
 * on included, saved or not — the badges add up to "Reset to theme"'s count;
 * the row tags mark unsaved edits.
 */
export function TokenGroups() {
  const { control } = useFormContext<DesignForm>();
  const { data } = useDesignQuery();
  const tokens = useWatch({ control, name: 'tokens' });
  const customCss = useWatch({ control, name: 'customCss' });
  // Groups folded by hand; every other on group is open, so a group a preset turns on opens too.
  const [folded, setFolded] = useState<string[]>(['customCss']);
  const isOn = (id: (typeof GROUPS)[number]['id']) => 'customCss' === id || tokens[id].enabled;
  const openIds = GROUPS.map(({ id }) => id).filter((id) => isOn(id) && !folded.includes(id));

  return (
    <Accordion
      type="multiple"
      value={openIds}
      onValueChange={(open) => setFolded(GROUPS.map(({ id }) => id).filter((id) => isOn(id) && !open.includes(id)))}
      className="flex flex-col gap-3"
    >
      {GROUPS.map(({ id, label, body: Body, summary }) => {
        const changed = 'customCss' === id || !tokens[id].enabled ? 0 : countTokenDiffs(tokens, data.defaultTokens, id);

        return (
          <AccordionItem key={id} value={id} disabled={!isOn(id)}>
            {/* The switch sits beside the trigger, not in it: a button inside a button is invalid HTML. */}
            <div className="flex items-center [&>h3]:flex-1">
              <AccordionTrigger className="disabled:cursor-default disabled:opacity-100 [&>svg]:hidden">
                <span className="flex-1">{label}</span>
                <span className="text-muted-foreground text-xs font-normal">
                  {isOn(id) ? summary(tokens, customCss) : __('Theme default', 'yaycheckout')}
                </span>
                {changed > 0 ? (
                  <Badge variant="outline" className="text-xs">
                    {sprintf(
                      /* translators: %d: number of tokens in this group that differ from the theme default */
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
        );
      })}
    </Accordion>
  );
}
