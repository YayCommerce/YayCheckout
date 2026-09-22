import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import { logger } from '@/lib/logger';
import type { LayoutSummary } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { LayoutSelect } from './LayoutSelect';

export function ExportCard() {
  const [layout, setLayout] = useState<LayoutSummary | null>(null);

  return (
    <Card className="bg-muted/30">
      <CardHeader>
        <CardTitle>{__('Export', 'yaycheckout')}</CardTitle>
        <CardDescription>{__('Downloads one layout with its tokens and slots.', 'yaycheckout')}</CardDescription>
      </CardHeader>
      <CardContent className="flex-1">
        <LayoutSelect value={layout} onChange={setLayout} />
      </CardContent>
      <CardFooter className="gap-3">
        <Button disabled={layout === null} onClick={() => logger.info('export layout', layout?.id)}>
          {__('Download .json', 'yaycheckout')}
        </Button>
        <span className="text-muted-foreground text-xs">{__('~34 KB', 'yaycheckout')}</span>
      </CardFooter>
    </Card>
  );
}
