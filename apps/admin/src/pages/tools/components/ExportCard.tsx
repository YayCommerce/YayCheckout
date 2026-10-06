import { useState } from 'react';
import { saveFile } from '@blocks-shared/export-file';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { useExportLayoutMutation } from '@/lib/queries/tools.queries';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { LoadingButton } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { LayoutSelect } from './LayoutSelect';

/** Bytes the JSON envelope adds around the markup; the hint is a hint, so one constant is enough. */
const ENVELOPE_OVERHEAD_BYTES = 250;

function fileSize(bytes: number): string {
  if (bytes < 1024) {
    /* translators: %d: file size in bytes */
    return sprintf(__('%d B', 'yaycheckout'), bytes);
  }

  /* translators: %s: file size in kilobytes */
  return sprintf(__('%s KB', 'yaycheckout'), (bytes / 1024).toFixed(1));
}

export function ExportCard() {
  const [layout, setLayout] = useState<LayoutSummary | null>(null);
  const exportMutation = useExportLayoutMutation();

  return (
    <Card className="bg-muted/30">
      <CardHeader>
        <CardTitle>{__('Export', 'yaycheckout')}</CardTitle>
        <CardDescription>
          {__('Downloads one layout as a .json file you can import on another site.', 'yaycheckout')}
        </CardDescription>
      </CardHeader>
      <CardContent className="flex-1">
        <LayoutSelect value={layout} onChange={setLayout} />
      </CardContent>
      <CardFooter className="gap-3">
        <LoadingButton
          loading={exportMutation.isPending}
          disabled={layout === null || exportMutation.isPending}
          onClick={() => {
            if (!layout) return;
            exportMutation.mutate(layout.id, {
              onSuccess: (file) => {
                saveFile(file.filename, file.json);
                toast.success(
                  sprintf(
                    /* translators: %s: layout title */
                    __('"%s" exported.', 'yaycheckout'),
                    layout.title,
                  ),
                );
              },
            });
          }}
        >
          {__('Download .json', 'yaycheckout')}
        </LoadingButton>
        {layout && (
          <span className="text-muted-foreground text-xs">{fileSize(layout.size + ENVELOPE_OVERHEAD_BYTES)}</span>
        )}
      </CardFooter>
    </Card>
  );
}
