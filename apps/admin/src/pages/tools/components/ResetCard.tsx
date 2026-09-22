import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import { logger } from '@/lib/logger';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { LayoutSelect } from './LayoutSelect';

export function ResetCard() {
  const [layoutId, setLayoutId] = useState<number | null>(null);

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
        <LayoutSelect value={layoutId} onChange={setLayoutId} />
        <p className="text-muted-foreground text-sm">
          {__('Revisions are kept, so you can roll back from the editor.', 'yaycheckout')}
        </p>
      </CardContent>
      <CardFooter>
        <Button variant="outline" disabled={layoutId === null} onClick={() => logger.info('reset layout', layoutId)}>
          {__('Reset layout', 'yaycheckout')}
        </Button>
      </CardFooter>
    </Card>
  );
}
