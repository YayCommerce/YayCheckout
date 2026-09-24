import { __ } from '@wordpress/i18n';

import { isPro } from '@/lib/plan';
import { cn } from '@/lib/utils';
import PageLayout from '@/pages/PageLayout';
import { ExportCard } from './components/ExportCard';
import { ExportImportCard } from './components/ExportImportCard';
import { ImportCard } from './components/ImportCard';
import { ResetCard } from './components/ResetCard';

export default function ToolsPage() {
  return (
    <PageLayout
      heading={__('Tools', 'yaycheckout')}
      description={__('Import, export and maintenance utilities for your checkout.', 'yaycheckout')}
    >
      <div className={cn('bg-card grid gap-6 rounded-md border p-6 md:grid-cols-2', isPro && 'xl:grid-cols-3')}>
        {isPro ? (
          <>
            <ExportCard />
            <ImportCard />
          </>
        ) : (
          <ExportImportCard />
        )}
        <ResetCard />
      </div>
    </PageLayout>
  );
}
