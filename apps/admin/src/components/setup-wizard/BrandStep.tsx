import { useFormContext, useWatch } from 'react-hook-form';
import { __, sprintf } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { useTemplate } from '@/lib/queries/template.queries';
import {
  BUTTON_STYLE_LABELS,
  CUSTOM_CSS_MAX_BYTES,
  RADIUS_RANGE,
  type DesignForm,
  type DesignTokens,
  type TokenSection,
} from '@/lib/schema/design.schema';
import { Button, LoadingButton } from '@/components/ui/button';
import {
  ColorPicker,
  ColorPickerContent,
  ColorPickerInput,
  ColorPickerPanel,
  ColorPickerTrigger,
} from '@/components/ui/color-picker';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ProTag, UpgradeButton } from '@/components/pro/ProGate';
import { FontFamilySelect } from '@/components/shared/FontFamilySelect';
import { PreviewPane } from '@/components/shared/PreviewPane';

const dirtyOptions = { shouldDirty: true } as const;

/**
 * Wizard step 3: a few global design tokens over a live preview, read from the
 * wizard's design form. Apply brand hands the values to the wizard to save.
 */
export function BrandStep({
  templateSlug,
  isApplying,
  error,
  onBack,
  onApply,
}: {
  templateSlug: string;
  isApplying: boolean;
  error: string | null;
  onBack: () => void;
  onApply: (values: DesignForm) => void;
}) {
  const { handleSubmit } = useFormContext<DesignForm>();
  const templateName = useTemplate(templateSlug)?.name;

  return (
    <div className="flex flex-col gap-6">
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">{__('Set the basics', 'yaycheckout')}</h2>
        <p className="text-muted-foreground text-sm">
          {__('These become your global design tokens. You can change them later in Design.', 'yaycheckout')}
        </p>
      </header>

      <div className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-6">
        <div className="flex flex-col gap-5 text-sm">
          <PrimaryColourField />
          <FontField />
          <CornerRadiusField />
          <ButtonStyleField />
          <CustomCssField />
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
            <PreviewPane isCompact />
          </div>
        </div>
      </div>

      {error && <p className="text-destructive text-sm">{error}</p>}

      <div className="flex justify-between gap-2 border-t pt-4">
        <Button variant="outline" onClick={onBack} disabled={isApplying}>
          {__('Back', 'yaycheckout')}
        </Button>
        <LoadingButton loading={isApplying} disabled={isApplying} onClick={handleSubmit(onApply)}>
          {__('Apply brand', 'yaycheckout')}
        </LoadingButton>
      </div>
    </div>
  );
}

/** Sets one token and switches its group on: a token only reaches the storefront while its group is on. */
function useSetToken() {
  const { setValue } = useFormContext<DesignForm>();
  // setValue cannot type a path built from generics; setToken's own signature keeps callers typed.
  const setPath = setValue as (name: string, value: unknown, options: typeof dirtyOptions) => void;

  return function setToken<S extends TokenSection, K extends keyof DesignTokens[S] & string>(
    section: S,
    key: K,
    value: DesignTokens[S][K],
  ) {
    setPath(`tokens.${section}.${key}`, value, dirtyOptions);
    setPath(`tokens.${section}.enabled`, true, dirtyOptions);
  };
}

function PrimaryColourField() {
  const { control } = useFormContext<DesignForm>();
  const primary = useWatch({ control, name: 'tokens.colours.primary' });
  const setToken = useSetToken();

  return (
    <div className="flex flex-col gap-2">
      <span className="font-medium">{__('Primary colour', 'yaycheckout')}</span>
      <ColorPicker value={primary} onValueChange={(value) => setToken('colours', 'primary', value)}>
        <ColorPickerTrigger className="h-9 w-full" />
        <ColorPickerContent>
          <ColorPickerPanel />
          <ColorPickerInput />
        </ColorPickerContent>
      </ColorPicker>
    </div>
  );
}

function FontField() {
  const { control, setValue } = useFormContext<DesignForm>();
  const isTypographyOn = useWatch({ control, name: 'tokens.typography.enabled' });
  const fontFamily = useWatch({ control, name: 'tokens.typography.fontFamily' });

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
        value={fontFamily}
        onChange={(font) => setValue('tokens.typography.fontFamily', font, dirtyOptions)}
        disabled={!isTypographyOn}
      />
      {!isTypographyOn && (
        <p className="text-muted-foreground text-xs">{__('Turn the toggle off to pick a font.', 'yaycheckout')}</p>
      )}
    </div>
  );
}

function CornerRadiusField() {
  const { control } = useFormContext<DesignForm>();
  const radius = useWatch({ control, name: 'tokens.buttons.radius' });
  const setToken = useSetToken();

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center justify-between">
        <span className="font-medium">{__('Corner radius', 'yaycheckout')}</span>
        <span className="text-muted-foreground">{radius}px</span>
      </div>
      {/* The basics step couples button and input radius; Design splits them. */}
      <Slider
        min={RADIUS_RANGE.min}
        max={RADIUS_RANGE.max}
        step={1}
        value={[radius]}
        onValueChange={([value]) => {
          setToken('buttons', 'radius', value);
          setToken('inputs', 'radius', value);
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

function ButtonStyleField() {
  const { control } = useFormContext<DesignForm>();
  const buttonStyle = useWatch({ control, name: 'tokens.buttons.style' });
  const setToken = useSetToken();

  return (
    <div className="flex flex-col gap-2">
      <span className="font-medium">{__('Button style', 'yaycheckout')}</span>
      <Segmented
        className="w-full"
        value={buttonStyle}
        onValueChange={(value) => value && setToken('buttons', 'style', value as typeof buttonStyle)}
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

function CustomCssField() {
  const { register } = useFormContext<DesignForm>();

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
        {...register('customCss')}
        rows={4}
        maxLength={CUSTOM_CSS_MAX_BYTES}
        placeholder={__('.yaycheckout-checkout { }', 'yaycheckout')}
        className="text-sm"
      />
    </label>
  );
}
