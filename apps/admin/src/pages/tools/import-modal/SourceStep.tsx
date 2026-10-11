import { TriangleAlert } from 'lucide-react';
import { __, _n, sprintf } from '@wordpress/i18n';

import type { ImportReview } from '@/lib/schema/tools.schema';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button, LoadingButton } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { SuccessHeader } from '@/components/shared/SuccessHeader';
import { SummaryStrip } from '@/components/shared/SummaryStrip';

export function SourceStep({
  review,
  isPending,
  error,
  onCancel,
  onImport,
}: {
  review: ImportReview;
  isPending: boolean;
  /** PHP's refusal, empty when there is none. */
  error: string;
  onCancel: () => void;
  onImport: () => void;
}) {
  return (
    <>
      <SuccessHeader
        title={sprintf(
          /* translators: %s: layout title */
          __('Ready to import "%s"', 'yaycheckout'),
          review.title,
        )}
        description={__('A new draft is created. Your current layouts stay as they are.', 'yaycheckout')}
      />

      <SummaryStrip
        items={[
          [__('Layout', 'yaycheckout'), review.title],
          [__('Checkout blocks', 'yaycheckout'), String(review.blocks)],
          [__('Assignment rules', 'yaycheckout'), ruleCountsLabel(review.ruleCounts)],
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

function ruleCountsLabel(counts: ImportReview['ruleCounts']): string {
  // A shape check only: PHP may still drop rules that read fine here.
  if (counts === null) return __('Unreadable', 'yaycheckout');
  if (counts.groups === 0) return __('None', 'yaycheckout');

  return [
    sprintf(
      /* translators: %d: number of rule groups */
      _n('%d group', '%d groups', counts.groups, 'yaycheckout'),
      counts.groups,
    ),
    sprintf(
      /* translators: %d: number of conditions */
      _n('%d condition', '%d conditions', counts.conditions, 'yaycheckout'),
      counts.conditions,
    ),
  ].join(' · ');
}
