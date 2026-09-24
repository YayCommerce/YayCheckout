import { Check } from 'lucide-react';
import { useNavigate } from 'react-router';
import { __, sprintf } from '@wordpress/i18n';

import type { Layout } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { SummaryStrip } from './StepStrip';

export function DraftStep({ layout, onClose }: { layout: Layout; onClose: () => void }) {
  const navigate = useNavigate();

  const go = (search = '') => {
    onClose();
    navigate({ pathname: '/layouts', search });
  };

  return (
    <>
      <div className="flex gap-3">
        <span className="bg-success/10 text-success flex size-8 shrink-0 items-center justify-center rounded-full">
          <Check className="size-4" />
        </span>
        <div className="space-y-1">
          <h3 className="text-base/snug font-semibold">
            {sprintf(
              /* translators: %s: layout title */
              __('Draft "%s" created — nothing is live', 'yaycheckout'),
              layout.title,
            )}
          </h3>
          <p className="text-muted-foreground text-sm">
            {__('Your active layout is still serving checkout.', 'yaycheckout')}
          </p>
        </div>
      </div>

      <SummaryStrip
        items={[
          [__('Layout', 'yaycheckout'), layout.title],
          [__('Status', 'yaycheckout'), __('Draft', 'yaycheckout')],
        ]}
      />

      <DialogFooter className="items-center border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-sm">
          {__('Publish and set active when you are happy with it.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => go()}>
            {__('Go to Layouts', 'yaycheckout')}
          </Button>
          {/* LayoutsPage reads ?edit=ID and opens its embedded editor, so this is the same editor the Edit action uses. */}
          <Button onClick={() => go(`?edit=${layout.id}`)}>{__('Open in editor', 'yaycheckout')}</Button>
        </div>
      </DialogFooter>
    </>
  );
}
