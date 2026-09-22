import { Check } from 'lucide-react';
import { useNavigate } from 'react-router';
import { __ } from '@wordpress/i18n';

import { logger } from '@/lib/logger';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { SummaryStrip } from './StepStrip';

export function DraftStep({ onClose }: { onClose: () => void }) {
  const navigate = useNavigate();

  return (
    <>
      <div className="flex gap-3">
        <span className="bg-success/10 text-success flex size-8 shrink-0 items-center justify-center rounded-full">
          <Check className="size-4" />
        </span>
        <div className="space-y-1">
          <h3 className="text-base/snug font-semibold">
            {__('Draft "Imported from FunnelKit" created — nothing is live', 'yaycheckout')}
          </h3>
          <p className="text-muted-foreground text-sm">
            {__(
              '18 blocks, 2 extra fields and a 2-step structure. FunnelKit is still enabled and still serving your live checkout.',
              'yaycheckout',
            )}
          </p>
        </div>
      </div>

      <SummaryStrip
        items={[
          [__('Layout', 'yaycheckout'), 'Imported from FunnelKit'],
          [__('Status', 'yaycheckout'), __('Draft', 'yaycheckout')],
          [__('Blocks', 'yaycheckout'), '18'],
        ]}
      />

      <DialogFooter className="items-center border-t pt-4 sm:justify-between">
        <p className="text-muted-foreground text-sm">
          {__('Publish and set active when you are happy with it.', 'yaycheckout')}
        </p>
        <div className="flex gap-2">
          <Button
            variant="outline"
            onClick={() => {
              onClose();
              navigate('/layouts');
            }}
          >
            {__('Go to Layouts', 'yaycheckout')}
          </Button>
          <Button onClick={() => logger.info('open imported draft in editor')}>
            {__('Open in editor', 'yaycheckout')}
          </Button>
        </div>
      </DialogFooter>
    </>
  );
}
