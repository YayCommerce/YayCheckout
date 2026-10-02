import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { __, sprintf } from '@wordpress/i18n';

import { activePresetIdFor } from '@/lib/helpers/design.helper';
import { getErrorMsg } from '@/lib/helpers/response.helper';
import { isPro } from '@/lib/plan';
import { useDesignQuery, useSaveDesignMutation } from '@/lib/queries/design.queries';
import { useCreateLayoutDraftMutation } from '@/lib/queries/layout.queries';
import { useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import {
  CUSTOM_CSS_MAX_BYTES,
  FONT_FAMILIES,
  FONT_FAMILY_KEYS,
  RADIUS_RANGE,
  type DesignForm,
} from '@/lib/schema/design.schema';
import { Button, LoadingButton } from '@/components/ui/button';
import {
  ColorPicker,
  ColorPickerContent,
  ColorPickerInput,
  ColorPickerPanel,
  ColorPickerTrigger,
} from '@/components/ui/color-picker';
import { Form } from '@/components/ui/form';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import { PreviewPane } from '@/components/shared/PreviewPane';
import { BUTTON_STYLE_LABELS } from '@/pages/design/components/ButtonsGroup';

// Radix Select treats value="" as "nothing selected", so the theme-default option needs a stand-in.
const THEME_DEFAULT_FONT = '__theme_default__';

/**
 * Wizard step 3: a few global design tokens over a live preview. Apply brand
 * saves the design, then creates the draft layout from the step 2 template.
 */
export function BrandStep({
  templateSlug,
  onBack,
  onApplied,
}: {
  templateSlug: string;
  onBack: () => void;
  onApplied: () => void;
}) {
  const { data: design } = useDesignQuery();
  const { data: library } = useTemplateLibraryQuery();
  const saveDesign = useSaveDesignMutation();
  const createDraft = useCreateLayoutDraftMutation();
  const [error, setError] = useState<string | null>(null);

  const form = useForm<DesignForm>({ defaultValues: { tokens: design.tokens, customCss: design.customCss } });
  const { watch, setValue } = form;
  const primary = watch('tokens.colours.primary');
  const inherit = watch('tokens.typography.inherit');
  const fontFamily = watch('tokens.typography.fontFamily');
  const radius = watch('tokens.buttons.radius');
  const buttonStyle = watch('tokens.buttons.style');

  const templateName = library?.templates.find((t) => t.slug === templateSlug)?.name;
  const isApplying = saveDesign.isPending || createDraft.isPending;

  async function onApply(values: DesignForm) {
    setError(null);
    try {
      await saveDesign.mutateAsync({
        form: values,
        presetId: activePresetIdFor(design.presets, design.presetId, values.tokens),
      });
      await createDraft.mutateAsync({ name: __('Checkout — draft', 'yaycheckout'), source: 'library', templateSlug });
      onApplied();
    } catch (e) {
      setError(await getErrorMsg(e));
    }
  }

  return (
    <Form {...form}>
      <div className="flex flex-col gap-6">
        <header className="space-y-1">
          <h2 className="text-lg font-semibold">{__('Set the basics', 'yaycheckout')}</h2>
          <p className="text-muted-foreground text-sm">
            {__('These become your global design tokens. You can change them later in Design.', 'yaycheckout')}
          </p>
        </header>

        <div className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-6">
          <div className="flex flex-col gap-5 text-sm">
            <div className="flex flex-col gap-2">
              <span className="font-medium">{__('Primary colour', 'yaycheckout')}</span>
              <ColorPicker
                value={primary}
                onValueChange={(value) => setValue('tokens.colours.primary', value, { shouldDirty: true })}
              >
                <ColorPickerTrigger className="h-9 w-full" />
                <ColorPickerContent>
                  <ColorPickerPanel />
                  <ColorPickerInput />
                </ColorPickerContent>
              </ColorPicker>
            </div>

            <div className="flex flex-col gap-2">
              <div className="flex items-center justify-between gap-2">
                <span className="font-medium">{__('Font', 'yaycheckout')}</span>
                <label className="text-muted-foreground flex items-center gap-2 text-xs">
                  {__('Inherit from theme', 'yaycheckout')}
                  <Switch
                    checked={inherit}
                    onCheckedChange={(value) => setValue('tokens.typography.inherit', value, { shouldDirty: true })}
                  />
                </label>
              </div>
              <Select
                value={fontFamily || THEME_DEFAULT_FONT}
                onValueChange={(value) =>
                  setValue(
                    'tokens.typography.fontFamily',
                    value === THEME_DEFAULT_FONT ? '' : (value as typeof fontFamily),
                    {
                      shouldDirty: true,
                    },
                  )
                }
                disabled={inherit}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {FONT_FAMILY_KEYS.map((font) => (
                    <SelectItem key={font} value={font || THEME_DEFAULT_FONT}>
                      {font ? FONT_FAMILIES[font] : __('Theme default', 'yaycheckout')}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {inherit && (
                <p className="text-muted-foreground text-xs">
                  {__('Turn the toggle off to pick a font.', 'yaycheckout')}
                </p>
              )}
            </div>

            <div className="flex flex-col gap-2">
              <div className="flex items-center justify-between">
                <span className="font-medium">{__('Corner radius', 'yaycheckout')}</span>
                <span className="text-muted-foreground">{radius}px</span>
              </div>
              {/* One radius for the basics: buttons and inputs move together; Design splits them later. */}
              <Slider
                min={RADIUS_RANGE.min}
                max={RADIUS_RANGE.max}
                step={1}
                value={[radius]}
                onValueChange={([value]) => {
                  setValue('tokens.buttons.radius', value, { shouldDirty: true });
                  setValue('tokens.inputs.radius', value, { shouldDirty: true });
                }}
                aria-label={__('Corner radius', 'yaycheckout')}
              />
              <div className="text-muted-foreground flex justify-between text-xs">
                <span>{RADIUS_RANGE.min}</span>
                <span>{RADIUS_RANGE.max}px</span>
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <span className="font-medium">{__('Button style', 'yaycheckout')}</span>
              <Segmented
                className="w-full"
                value={buttonStyle}
                onValueChange={(value) =>
                  value && setValue('tokens.buttons.style', value as typeof buttonStyle, { shouldDirty: true })
                }
              >
                {Object.entries(BUTTON_STYLE_LABELS).map(([value, label]) => (
                  <SegmentedItem key={value} value={value} className="flex-1">
                    {label}
                  </SegmentedItem>
                ))}
              </Segmented>
            </div>

            {isPro ? (
              <label className="flex flex-col gap-2">
                <span className="font-medium">{__('Custom CSS', 'yaycheckout')}</span>
                <Textarea
                  {...form.register('customCss')}
                  rows={4}
                  maxLength={CUSTOM_CSS_MAX_BYTES}
                  placeholder={__('.yaycheckout-checkout { }', 'yaycheckout')}
                  className="text-sm"
                />
              </label>
            ) : (
              <div className="border-muted-foreground/40 bg-muted/40 flex flex-col gap-3 rounded-md border border-dashed p-3">
                <div className="flex items-center gap-2">
                  <ProTag />
                  <span className="text-muted-foreground text-sm">
                    {__('Custom CSS & token overrides', 'yaycheckout')}
                  </span>
                </div>
                <div className="bg-muted h-12 rounded-sm" />
                <UpgradeButton className="self-start" />
              </div>
            )}
          </div>

          <div className="flex min-w-0 flex-col gap-2">
            <span className="text-muted-foreground text-xs tracking-wide uppercase">
              {templateName
                ? sprintf(
                    /* translators: %s: template name */
                    __('Live preview · 60%% scale · %s', 'yaycheckout'),
                    templateName,
                  )
                : __('Live preview · 60% scale', 'yaycheckout')}
            </span>
            {/* zoom, not transform: the mock lays out at full desktop width, then shrinks to fit the column. */}
            <div style={{ zoom: 0.6 }}>
              <PreviewPane compact />
            </div>
          </div>
        </div>

        {error && <p className="text-destructive text-sm">{error}</p>}

        <div className="flex justify-between gap-2 border-t pt-4">
          <Button variant="outline" onClick={onBack} disabled={isApplying}>
            {__('Back', 'yaycheckout')}
          </Button>
          <LoadingButton loading={isApplying} disabled={isApplying} onClick={form.handleSubmit(onApply)}>
            {__('Apply brand', 'yaycheckout')}
          </LoadingButton>
        </div>
      </div>
    </Form>
  );
}
