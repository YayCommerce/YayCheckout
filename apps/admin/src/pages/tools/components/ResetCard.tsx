import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { LayoutSelect } from './LayoutSelect';
import { ResetModal } from './ResetModal';

export function ResetCard() {
  const [layout, setLayout] = useState<LayoutSummary | null>(null);
  const [confirming, setConfirming] = useState(false);

  return (
    <Card className="bg-muted/30">
      <CardHeader>
        <CardTitle>{__('Reset to its template', 'yaycheckout')}</CardTitle>
        <CardDescription>
          {__("Replaces the selected layout's blocks and design with the template it was created from.", 'yaycheckout')}
        </CardDescription>
      </CardHeader>
      <CardContent className="flex-1 space-y-3">
        <LayoutSelect value={layout} onChange={setLayout} />
        <p className="text-muted-foreground text-sm">
          {__('Revisions are kept, so you can roll back from the editor.', 'yaycheckout')}
        </p>
      </CardContent>
      <CardFooter>
        <Button disabled={layout === null} onClick={() => setConfirming(true)}>
          {__('Reset layout', 'yaycheckout')}
        </Button>
      </CardFooter>
      <ResetModal layout={confirming ? layout : null} onOpenChange={setConfirming} onReset={setLayout} />
    </Card>
  );
}
