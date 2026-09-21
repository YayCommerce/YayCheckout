import { CSSProperties, useState } from 'react';
import { Monitor, Smartphone, Tablet } from 'lucide-react';
import { useFormContext } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import type { DesignForm } from '@/lib/schema/design.schema';
import { cn } from '@/lib/utils';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';

type Viewport = 'desktop' | 'tablet' | 'mobile';

const VIEWPORT_WIDTH: Record<Viewport, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '390px',
};

const SECTION_LABEL_STYLE: CSSProperties = { fontSize: 11, letterSpacing: '0.08em', opacity: 0.6 };

// What "Theme default" renders as here, since the preview has no theme to inherit from.
const DEFAULT_FONT_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

/**
 * Right column: device switch over a mock of the real checkout layout
 * (contact, shipping, payment, order summary), restyled live from the
 * form's current tokens so every token has visible surface to land on.
 */
export function PreviewPane() {
  const [viewport, setViewport] = useState<Viewport>('desktop');
  const { watch } = useFormContext<DesignForm>();
  const tokens = watch('tokens');
  const customCss = watch('customCss');
  const { colours, typography, spacing, buttons, inputs } = tokens;

  const gap = 10 * spacing.scale;

  const rootStyle: CSSProperties = {
    color: colours.text,
    backgroundColor: colours.background,
    fontFamily: typography.inherit || '' === typography.fontFamily ? DEFAULT_FONT_STACK : typography.fontFamily,
    fontSize: typography.inherit ? undefined : `${typography.baseSize}px`,
  };

  const fieldStyle: CSSProperties = {
    borderRadius: 'underline' === inputs.style ? 0 : inputs.radius,
    borderWidth: 'underline' === inputs.style ? '0 0 1px 0' : 1,
    borderStyle: 'solid',
    borderColor: colours.border,
    backgroundColor: colours.background,
    height: 'small' === inputs.size ? 32 : 'large' === inputs.size ? 48 : 40,
  };

  const buttonStyle: CSSProperties = {
    borderRadius: buttons.radius,
    borderWidth: 1,
    borderStyle: 'solid',
    borderColor: colours.primary,
    backgroundColor: 'outline' === buttons.style ? 'transparent' : colours.primary,
    color: 'outline' === buttons.style ? colours.primary : colours.background,
    padding: '10px 20px',
    fontWeight: 600,
  };

  return (
    <div className="bg-background flex min-w-0 flex-1 flex-col gap-3 rounded-lg border p-4">
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

      <div
        className={cn(
          'flex min-h-160 flex-1 justify-center overflow-hidden rounded-lg border',
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

          <div className="yaycheckout-checkout flex flex-col gap-6 p-6 md:flex-row" style={rootStyle}>
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
              className="bg-muted/40 flex w-full flex-col md:w-64"
              style={{ gap, padding: 1.5 * gap, borderRadius: inputs.radius }}
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
