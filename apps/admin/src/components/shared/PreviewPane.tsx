import { CSSProperties, useState } from 'react';
import { Monitor, Smartphone, Tablet } from 'lucide-react';
import { useFormContext } from 'react-hook-form';
import { __, sprintf } from '@wordpress/i18n';

import { isGroupOn } from '@/lib/helpers/design.helper';
import { FONT_FAMILIES, type DesignForm } from '@/lib/schema/design.schema';
import { cn } from '@/lib/utils';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';
import { GROUPS } from '@/pages/design/components/TokenGroups';

type Viewport = 'desktop' | 'tablet' | 'mobile';

const VIEWPORT_WIDTH: Record<Viewport, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '390px',
};

const SECTION_LABEL_STYLE: CSSProperties = { fontSize: 11, letterSpacing: '0.08em', opacity: 0.6 };

// What "Theme default" renders as here, since the preview has no theme to inherit from; also
// the fallback behind a picked font. Mirrors DesignStyles::FONT_FALLBACK (PHP), which the storefront prints.
const DEFAULT_FONT_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

// What a theme-default section draws as, since the admin has no theme CSS.
const NEUTRAL = {
  colours: { primary: '#71717a', text: '#3f3f46', background: '#ffffff', border: '#d4d4d8' },
  radius: 6,
  inputHeight: 40,
};

/**
 * Device switch over a mock of the real checkout layout (contact, shipping,
 * payment, order summary), restyled live from the form's current tokens so
 * every token has visible surface to land on.
 *
 * @param compact Desktop mock only: no device switch, no minimum height (the setup wizard's preview).
 */
export function PreviewPane({ compact = false }: { compact?: boolean }) {
  const [viewport, setViewport] = useState<Viewport>('desktop');
  const { watch } = useFormContext<DesignForm>();
  const tokens = watch('tokens');
  const customCss = watch('customCss');
  const { typography, spacing, buttons, inputs } = tokens;
  const colours = tokens.colours.enabled ? tokens.colours : NEUTRAL.colours;
  const isButtonFill = !buttons.enabled || 'fill' === buttons.style;
  const isInputBoxed = !inputs.enabled || 'boxed' === inputs.style;
  const inputRadius = inputs.enabled ? inputs.radius : NEUTRAL.radius;
  const themeGroups = GROUPS.filter(({ id }) => !isGroupOn(tokens, id)).map(({ label }) => label);

  const gap = 10 * (spacing.enabled ? spacing.scale : 1);

  const rootStyle: CSSProperties = {
    color: colours.text,
    backgroundColor: colours.background,
    fontFamily:
      !typography.enabled || '' === typography.fontFamily
        ? DEFAULT_FONT_STACK
        : `"${FONT_FAMILIES[typography.fontFamily]}", ${DEFAULT_FONT_STACK}`,
    fontSize: !typography.enabled ? undefined : `${typography.baseSize}px`,
  };

  const fieldStyle: CSSProperties = {
    borderRadius: isInputBoxed ? inputRadius : 0,
    borderWidth: isInputBoxed ? 1 : '0 0 1px 0',
    borderStyle: 'solid',
    borderColor: colours.border,
    backgroundColor: colours.background,
    // Mirrors DesignStyles::INPUT_HEIGHTS (PHP), which the storefront prints.
    height: inputs.enabled ? { small: 32, medium: 40, large: 48 }[inputs.size] : NEUTRAL.inputHeight,
  };

  const buttonStyle: CSSProperties = {
    borderRadius: buttons.enabled ? buttons.radius : NEUTRAL.radius,
    borderWidth: 1,
    borderStyle: 'solid',
    borderColor: colours.primary,
    backgroundColor: isButtonFill ? colours.primary : 'transparent',
    color: isButtonFill ? colours.background : colours.primary,
    padding: '10px 20px',
    fontWeight: 600,
  };

  return (
    <div className={cn('bg-background flex min-w-0 flex-1 flex-col gap-3', !compact && 'rounded-lg border p-4')}>
      {!compact && (
        <div className="flex items-center gap-2">
          <span className="flex-1 text-sm font-semibold">{__('Preview', 'yaycheckout')}</span>
          <Segmented size="sm" shape="square" value={viewport} onValueChange={(v) => v && setViewport(v as Viewport)}>
            <SegmentedItem value="desktop" aria-label={__('Desktop', 'yaycheckout')}>
              <Monitor />
            </SegmentedItem>
            <SegmentedItem value="tablet" aria-label={__('Tablet', 'yaycheckout')}>
              <Tablet />
            </SegmentedItem>
            <SegmentedItem value="mobile" aria-label={__('Mobile', 'yaycheckout')}>
              <Smartphone />
            </SegmentedItem>
          </Segmented>
        </div>
      )}
      {themeGroups.length ? (
        <p className="text-muted-foreground m-0 text-xs">
          {sprintf(
            /* translators: %s: comma-separated design group names, e.g. Colours, Buttons */
            __('Grey parts follow your theme on the storefront: %s.', 'yaycheckout'),
            themeGroups.join(', '),
          )}
        </p>
      ) : null}

      <div
        className={cn(
          'flex flex-1 justify-center overflow-hidden rounded-lg border',
          !compact && 'min-h-160',
          viewport !== 'desktop' && 'bg-muted p-4',
        )}
      >
        {/* CSS nesting keeps a stray `* { display: none }` inside the preview instead of hiding the admin. */}
        {customCss ? <style>{`.yaycheckout-preview { ${customCss} }`}</style> : null}

        <div
          style={{ width: VIEWPORT_WIDTH[viewport] }}
          className={cn(
            'yaycheckout-preview bg-background flex max-w-full flex-col',
            viewport !== 'desktop' && 'self-start rounded-md border shadow-sm',
          )}
        >
          <div className="text-muted-foreground flex items-center gap-2 border-b px-3 py-2 font-mono text-[10px]">
            <span className="border-muted-foreground/50 size-2.5 rounded-full border" />
            store.example/checkout
          </div>

          <div
            className={cn('yaycheckout-checkout flex gap-6 p-6', viewport === 'mobile' ? 'flex-col' : 'flex-row')}
            style={rootStyle}
          >
            <div className="flex flex-1 flex-col" style={{ gap: 1.5 * gap }}>
              <p className="m-0 text-xl font-bold">{__('Checkout', 'yaycheckout')}</p>

              <div className="flex flex-col" style={{ gap }}>
                <p className="m-0" style={SECTION_LABEL_STYLE}>
                  {__('CONTACT', 'yaycheckout')}
                </p>
                <input style={fieldStyle} readOnly />
              </div>

              <div className="flex flex-col" style={{ gap }}>
                <p className="m-0" style={SECTION_LABEL_STYLE}>
                  {__('SHIPPING', 'yaycheckout')}
                </p>
                <input style={fieldStyle} readOnly />
                <div className="grid grid-cols-2" style={{ gap }}>
                  <input style={fieldStyle} readOnly />
                  <input style={fieldStyle} readOnly />
                </div>
                <input style={fieldStyle} readOnly />
              </div>

              <div className="flex flex-col" style={{ gap }}>
                <p className="m-0" style={SECTION_LABEL_STYLE}>
                  {__('PAYMENT', 'yaycheckout')}
                </p>
                <input style={fieldStyle} readOnly />
                <input style={fieldStyle} readOnly />
              </div>

              <div className="mt-auto flex items-center justify-between pt-4">
                <span className="font-semibold">{__('Total £84.00', 'yaycheckout')}</span>
                <button type="button" style={buttonStyle}>
                  {__('Place order', 'yaycheckout')}
                </button>
              </div>
            </div>

            <div
              className={cn('bg-muted/40 flex flex-col', viewport === 'mobile' ? 'w-full' : 'w-64 shrink-0')}
              style={{ gap, padding: 1.5 * gap, borderRadius: inputRadius }}
            >
              <p className="m-0" style={SECTION_LABEL_STYLE}>
                {__('ORDER SUMMARY', 'yaycheckout')}
              </p>

              {[0, 1].map((row) => (
                <div key={row} className="flex items-center" style={{ gap }}>
                  <div className="bg-muted-foreground/15 size-12 shrink-0 rounded" />
                  <div className="flex flex-1 flex-col gap-1.5">
                    <div className="bg-muted-foreground/15 h-2.5 w-full rounded" />
                    <div className="bg-muted-foreground/15 h-2.5 w-2/3 rounded" />
                  </div>
                </div>
              ))}

              <div className="border-t pt-3" style={{ borderColor: colours.border }}>
                <div className="flex items-center justify-between text-sm">
                  <span>{__('Subtotal', 'yaycheckout')}</span>
                  <span>£79.00</span>
                </div>
                <div className="mt-1.5 flex items-center justify-between text-sm">
                  <span>{__('Shipping', 'yaycheckout')}</span>
                  <span>£5.00</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
