import type { ReactNode } from 'react';
import { useFormContext } from 'react-hook-form';
import { __, sprintf } from '@wordpress/i18n';

import { countDirtyLeaves } from '@/lib/helpers/form.helper';
import type { DesignForm, TokenSection } from '@/lib/schema/design.schema';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { ColoursGroup } from './ColoursGroup';
import { TypographyGroup } from './TypographyGroup';

const GROUPS: { id: TokenSection | 'customCss'; label: string; body?: () => ReactNode }[] = [
  { id: 'colours', label: __('Colours', 'yaycheckout'), body: ColoursGroup },
  { id: 'typography', label: __('Typography', 'yaycheckout'), body: TypographyGroup },
  { id: 'spacing', label: __('Spacing & radius', 'yaycheckout') },
  { id: 'buttons', label: __('Buttons', 'yaycheckout') },
  { id: 'inputs', label: __('Inputs', 'yaycheckout') },
  { id: 'customCss', label: __('Custom CSS', 'yaycheckout') },
];

/**
 * The token editor: one collapsible group per section, Colours open by default.
 * Each header counts the group's edits since the last save — the same measure
 * as the "changed" row tags.
 */
export function TokenGroups() {
  // Read during render so react-hook-form's proxy subscribes to dirtyFields.
  const { formState } = useFormContext<DesignForm>();
  const { dirtyFields } = formState;

  return (
    <Accordion type="multiple" defaultValue={['colours']} className="flex flex-col gap-3">
      {GROUPS.map(({ id, label, body: Body }) => {
        const changed = countDirtyLeaves('customCss' === id ? dirtyFields.customCss : dirtyFields.tokens?.[id]);

        return (
          <AccordionItem key={id} value={id}>
            <AccordionTrigger>
              <span className="flex-1">{label}</span>
              {changed > 0 ? (
                <Badge variant="outline" className="font-mono text-[10px]">
                  {sprintf(
                    /* translators: %d: number of tokens in this group edited since the last save */
                    __('%d changed', 'yaycheckout'),
                    changed,
                  )}
                </Badge>
              ) : null}
            </AccordionTrigger>
            <AccordionContent>
              {Body ? (
                <Body />
              ) : (
                <p className="text-muted-foreground px-4 py-3 text-xs">{__('Coming soon.', 'yaycheckout')}</p>
              )}
            </AccordionContent>
          </AccordionItem>
        );
      })}
    </Accordion>
  );
}
