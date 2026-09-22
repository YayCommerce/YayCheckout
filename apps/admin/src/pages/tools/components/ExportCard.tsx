import { useState } from 'react';
import { toast } from 'sonner';
import { __, sprintf } from '@wordpress/i18n';

import { useLayoutQuery } from '@/lib/queries/layout.queries';
import { useExportLayoutMutation } from '@/lib/queries/tools.queries';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import type { LayoutExportFile } from '@/lib/schema/tools.schema';
import { LoadingButton } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { LayoutSelect } from './LayoutSelect';

/** Bytes the envelope adds around the markup; only for the size hint, so a rough constant is enough. */
const ENVELOPE_OVERHEAD_BYTES = 250;

function saveFile({ filename, json }: LayoutExportFile) {
  const url = URL.createObjectURL(new Blob([json], { type: 'application/json' }));
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  // Deferred: Safari can drop the download if the URL is revoked in the same tick as the click.
  setTimeout(() => URL.revokeObjectURL(url), 0);
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
                saveFile(file);
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
        {layout && <SizeHint id={layout.id} />}
      </CardFooter>
    </Card>
  );
}

function SizeHint({ id }: { id: number }) {
  const detail = useLayoutQuery(id);
  if (!detail.data) return null;

  const kb = Math.max(1, Math.round((detail.data.content.length + ENVELOPE_OVERHEAD_BYTES) / 1024));

  return (
    <span className="text-muted-foreground text-xs">
      {sprintf(
        /* translators: %d: approximate file size in kilobytes */
        __('~%d KB', 'yaycheckout'),
        kb,
      )}
    </span>
  );
}
