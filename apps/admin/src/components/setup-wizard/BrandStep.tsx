import { useState } from 'react';
import type { UseFormReturn } from 'react-hook-form';
import { __, sprintf } from '@wordpress/i18n';

import { activePresetIdFor } from '@/lib/helpers/design.helper';
import { getErrorMsg } from '@/lib/helpers/response.helper';
import { isPro } from '@/lib/plan';
import { useDesignQuery, useSaveDesignMutation } from '@/lib/queries/design.queries';
import { useCreateLayoutDraftMutation } from '@/lib/queries/layout.queries';
import { useTemplateLibraryQuery } from '@/lib/queries/template.queries';
import { BUTTON_STYLE_LABELS, CUSTOM_CSS_MAX_BYTES, RADIUS_RANGE, type DesignForm } from '@/lib/schema/design.schema';
import type { Layout } from '@/lib/schema/layout.schema';
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
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import { FontFamilySelect } from '@/components/shared/FontFamilySelect';
import { PreviewPane } from '@/components/shared/PreviewPane';

type BrandForm = UseFormReturn<DesignForm>;

// A token only reaches the storefront while its group is on, so touching one switches its group on.
const dirtyOptions = { shouldDirty: true } as const;

/**
 * Wizard step 3: a few global design tokens over a live preview. Apply brand
 * creates the draft layout from the step 2 template, then saves the design.
 *
 * @param form Owned by the wizard so Back and forth keeps the edits.
 */
export function BrandStep({
  form,
  templateSlug,
  onBack,
  onApplied,
}: {
  form: BrandForm;
  templateSlug: string;
  onBack: () => void;
  onApplied: (draft: Layout) => void;
}) {
  const { data: design } = useDesignQuery();
  const { data: library } = useTemplateLibraryQuery();
  const saveDesign = useSaveDesignMutation();
  const createDraft = useCreateLayoutDraftMutation();
  const [error, setError] = useState<string | null>(null);
  // Kept across a failed design save, so retrying Apply never makes a second draft.
  const [draft, setDraft] = useState<Layout | null>(null);

  const templateName = library?.templates.find((t) => t.slug === templateSlug)?.name;
  const isApplying = saveDesign.isPending || createDraft.isPending;

  async function onApply(values: DesignForm) {
    setError(null);
    try {
      // Draft first: if it fails (e.g. Free at its layout limit), the saved design is still untouched.
      const created =
        draft ??
        (await createDraft.mutateAsync({
          name: __('Checkout — draft', 'yaycheckout'),
          source: 'library',
          templateSlug,
        }));
      setDraft(created);
      await saveDesign.mutateAsync({
        form: values,
        presetId: activePresetIdFor(design.presets, design.presetId, values.tokens),
      });
      onApplied(created);
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
            <PrimaryColourField form={form} />
            <FontField form={form} />
            <CornerRadiusField form={form} />
            <ButtonStyleField form={form} />
            <CustomCssField form={form} />
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

function PrimaryColourField({ form }: { form: BrandForm }) {
  const { watch, setValue } = form;

  return (
    <div className="flex flex-col gap-2">
      <span className="font-medium">{__('Primary colour', 'yaycheckout')}</span>
      <ColorPicker
        value={watch('tokens.colours.primary')}
        onValueChange={(value) => {
          setValue('tokens.colours.primary', value, dirtyOptions);
          setValue('tokens.colours.enabled', true, dirtyOptions);
        }}
      >
        <ColorPickerTrigger className="h-9 w-full" />
        <ColorPickerContent>
          <ColorPickerPanel />
          <ColorPickerInput />
        </ColorPickerContent>
      </ColorPicker>
    </div>
  );
}

function FontField({ form }: { form: BrandForm }) {
  const { watch, setValue } = form;
  const isTypographyOn = watch('tokens.typography.enabled');

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center justify-between gap-2">
        <span className="font-medium">{__('Font', 'yaycheckout')}</span>
        <label className="text-muted-foreground flex items-center gap-2 text-xs">
          {__('Inherit from theme', 'yaycheckout')}
          <Switch
            checked={!isTypographyOn}
            onCheckedChange={(inherit) => setValue('tokens.typography.enabled', !inherit, dirtyOptions)}
          />
        </label>
      </div>
      <FontFamilySelect
        value={watch('tokens.typography.fontFamily')}
        onChange={(font) => setValue('tokens.typography.fontFamily', font, dirtyOptions)}
        disabled={!isTypographyOn}
      />
      {!isTypographyOn && (
        <p className="text-muted-foreground text-xs">{__('Turn the toggle off to pick a font.', 'yaycheckout')}</p>
      )}
    </div>
  );
}

function CornerRadiusField({ form }: { form: BrandForm }) {
  const { watch, setValue } = form;
  const radius = watch('tokens.buttons.radius');

  return (
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
          setValue('tokens.buttons.radius', value, dirtyOptions);
          setValue('tokens.inputs.radius', value, dirtyOptions);
          setValue('tokens.buttons.enabled', true, dirtyOptions);
          setValue('tokens.inputs.enabled', true, dirtyOptions);
        }}
        aria-label={__('Corner radius', 'yaycheckout')}
      />
      <div className="text-muted-foreground flex justify-between text-xs">
        <span>{RADIUS_RANGE.min}</span>
        <span>{RADIUS_RANGE.max}px</span>
      </div>
    </div>
  );
}

function ButtonStyleField({ form }: { form: BrandForm }) {
  const { watch, setValue } = form;
  const buttonStyle = watch('tokens.buttons.style');

  return (
    <div className="flex flex-col gap-2">
      <span className="font-medium">{__('Button style', 'yaycheckout')}</span>
      <Segmented
        className="w-full"
        value={buttonStyle}
        onValueChange={(value) => {
          if (!value) return;
          setValue('tokens.buttons.style', value as typeof buttonStyle, dirtyOptions);
          setValue('tokens.buttons.enabled', true, dirtyOptions);
        }}
      >
        {Object.entries(BUTTON_STYLE_LABELS).map(([value, label]) => (
          <SegmentedItem key={value} value={value} className="flex-1">
            {label}
          </SegmentedItem>
        ))}
      </Segmented>
    </div>
  );
}

function CustomCssField({ form }: { form: BrandForm }) {
  if (!isPro) {
    return (
      <div className="border-muted-foreground/40 bg-muted/40 flex flex-col gap-3 rounded-md border border-dashed p-3">
        <div className="flex items-center gap-2">
          <ProTag />
          <span className="text-muted-foreground text-sm">{__('Custom CSS & token overrides', 'yaycheckout')}</span>
        </div>
        <div className="bg-muted h-12 rounded-sm" />
        <UpgradeButton className="self-start" />
      </div>
    );
  }

  return (
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
  );
}
