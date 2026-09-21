import { useState } from 'react';
import { Monitor, Smartphone, Tablet } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { Segmented, SegmentedItem } from '@/components/ui/segmented';

type Viewport = 'desktop' | 'tablet' | 'mobile';

const VIEWPORT_WIDTH: Record<Viewport, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '390px',
};

/** Right column: device switch over a stage whose width follows the chosen viewport. */
export function PreviewPane() {
  const [viewport, setViewport] = useState<Viewport>('desktop');

  return (
    <div className="flex min-w-0 flex-1 flex-col gap-3">
      <div className="flex items-center gap-2">
        <span className="text-muted-foreground flex-1 font-mono text-[10px] tracking-widest uppercase">
          {__('Preview', 'yaycheckout')}
        </span>
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
          'bg-muted flex min-h-160 flex-1 justify-center rounded-lg border',
          viewport !== 'desktop' && 'p-4',
        )}
      >
        <div
          style={{ width: VIEWPORT_WIDTH[viewport] }}
          className={cn(
            'bg-background text-muted-foreground flex max-w-full items-center justify-center text-sm',
            viewport !== 'desktop' && 'rounded-md border shadow-sm',
          )}
        >
          {__('Checkout preview', 'yaycheckout')}
        </div>
      </div>
    </div>
  );
}
