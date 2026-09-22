import { Check } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { SummaryStrip } from './StepStrip';

export function SourceStep({ onCancel, onNext }: { onCancel: () => void; onNext: () => void }) {
  return (
    <>
      <div className="flex gap-3">
        <span className="bg-success/10 text-success flex size-8 shrink-0 items-center justify-center rounded-full">
          <Check className="size-4" />
        </span>
        <div className="space-y-1">
          <h3 className="text-base/snug font-semibold">
            {__('We found FunnelKit checkout "Main checkout" with 16 fields and 2 custom fields', 'yaycheckout')}
          </h3>
          <p className="text-muted-foreground text-sm">
            {__(
              'Nothing is changed yet. The next step shows exactly which YayCheckout block each field becomes.',
              'yaycheckout',
            )}
          </p>
        </div>
      </div>

      <SummaryStrip
        items={[
          [__('Source', 'yaycheckout'), 'FunnelKit 3.4.2'],
          [__('Checkout', 'yaycheckout'), 'Main checkout'],
          [__('Fields', 'yaycheckout'), '16 + 2 custom'],
          [__('Steps', 'yaycheckout'), '2'],
        ]}
      />

      <DialogFooter className="items-center border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-sm">{__('FunnelKit stays installed and enabled.', 'yaycheckout')}</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onCancel}>
            {__('Cancel', 'yaycheckout')}
          </Button>
          <Button onClick={onNext}>{__('Preview mapping', 'yaycheckout')}</Button>
        </div>
      </DialogFooter>
    </>
  );
}
