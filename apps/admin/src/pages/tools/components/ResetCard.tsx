import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { ResetModal } from '../reset-modal/ResetModal';
import { LayoutSelect } from './LayoutSelect';

export function ResetCard() {
  const [layout, setLayout] = useState<LayoutSummary | null>(null);
  const [confirming, setConfirming] = useState(false);

  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Reset to default layout', 'yaycheckout')}</CardTitle>
        <CardDescription>
          {__(
            'Replaces the selected layout with the blank starting layout: root plus the required sections.',
            'yaycheckout',
          )}
        </CardDescription>
      </CardHeader>
      <CardContent className="flex-1 space-y-3">
        <LayoutSelect value={layout} onChange={setLayout} />
        <p className="text-muted-foreground text-sm">
          {__('Revisions are kept, so you can roll back from the editor.', 'yaycheckout')}
        </p>
      </CardContent>
      <CardFooter>
        <Button variant="outline" disabled={layout === null} onClick={() => setConfirming(true)}>
          {__('Reset layout', 'yaycheckout')}
        </Button>
      </CardFooter>
      <ResetModal layout={confirming ? layout : null} onOpenChange={setConfirming} />
    </Card>
  );
}
