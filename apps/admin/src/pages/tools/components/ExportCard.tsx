import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import { logger } from '@/lib/logger';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { LayoutSelect } from './LayoutSelect';

export function ExportCard() {
  const [layoutId, setLayoutId] = useState<number | null>(null);

  return (
    <Card>
      <CardHeader>
        <CardTitle>{__('Export', 'yaycheckout')}</CardTitle>
        <CardDescription>{__('Downloads one layout with its tokens and slots.', 'yaycheckout')}</CardDescription>
      </CardHeader>
      <CardContent className="flex-1">
        <LayoutSelect value={layoutId} onChange={setLayoutId} />
      </CardContent>
      <CardFooter className="gap-3">
        <Button variant="outline" disabled={layoutId === null} onClick={() => logger.info('export layout', layoutId)}>
          {__('Download .json', 'yaycheckout')}
        </Button>
        <span className="text-muted-foreground text-xs">{__('~34 KB', 'yaycheckout')}</span>
      </CardFooter>
    </Card>
  );
}
