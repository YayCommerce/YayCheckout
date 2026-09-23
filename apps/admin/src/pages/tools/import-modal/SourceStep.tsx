import { Check, TriangleAlert } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import type { ImportReview } from '@/lib/schema/tools.schema';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button, LoadingButton } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { SummaryStrip } from './StepStrip';

export function SourceStep({
  review,
  isPending,
  error,
  onCancel,
  onImport,
}: {
  review: ImportReview;
  isPending: boolean;
  /** PHP's refusal, empty when there is none: only PHP knows which blocks this site registers. */
  error: string;
  onCancel: () => void;
  onImport: () => void;
}) {
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
              __('Ready to import "%s"', 'yaycheckout'),
              review.title,
            )}
          </h3>
          <p className="text-muted-foreground text-sm">
            {__('A new draft is created. Your current layouts stay as they are.', 'yaycheckout')}
          </p>
        </div>
      </div>

      <SummaryStrip
        items={[
          [__('Layout', 'yaycheckout'), review.title],
          [
            __('Blocks', 'yaycheckout'),
            sprintf(
              /* translators: %d: number of blocks */
              _n('%d block', '%d blocks', review.blocks, 'yaycheckout'),
              review.blocks,
            ),
          ],
        ]}
      />

      {error !== '' && (
        <Alert variant="outline" destructive className="border-destructive/30 bg-destructive/5">
          <TriangleAlert />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <DialogFooter className="border-t pt-4">
        <Button variant="outline" onClick={onCancel} disabled={isPending}>
          {__('Cancel', 'yaycheckout')}
        </Button>
        <LoadingButton loading={isPending} disabled={isPending} onClick={onImport}>
          {__('Import', 'yaycheckout')}
        </LoadingButton>
      </DialogFooter>
    </>
  );
}
