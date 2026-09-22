import { __ } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { ProTag, UPGRADE_URL, UpgradeButton } from '@/components/pro/ProGate';

/** Free-build teaser for Export and Import: the card is the upsell, so no ProGate overlay. */
export function ExportImportCard() {
  return (
    <Card className="border-dashed">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ProTag />
          {__('Export / Import', 'yaycheckout')}
        </CardTitle>
        <CardDescription>{__('Move a layout between sites as a .json file.', 'yaycheckout')}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="bg-muted h-28 rounded-md" />
        <div className="flex gap-3">
          <Button variant="outline" disabled>
            {__('Download .json', 'yaycheckout')}
          </Button>
          <Button variant="outline" disabled>
            {__('Import file', 'yaycheckout')}
          </Button>
        </div>
      </CardContent>
      <CardFooter className="flex-col items-start gap-3">
        <p className="text-sm">{__("Copy your layout from the editor's Code view for free", 'yaycheckout')}</p>
        <UpgradeButton onClick={() => window.open(UPGRADE_URL)} />
      </CardFooter>
    </Card>
  );
}
