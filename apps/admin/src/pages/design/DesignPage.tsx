import { Plus } from 'lucide-react';
import { __ } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ProGate, ProTag } from '@/components/pro/ProGate';
import PageLayout from '@/pages/PageLayout';
import { DesignActions } from './components/DesignActions';
import { PreviewPane } from './components/PreviewPane';

const PRESET_NAMES = [__('Default', 'yaycheckout'), __('Dark', 'yaycheckout'), __('B2B', 'yaycheckout')];

const GROUP_LABELS = [
  __('Colours', 'yaycheckout'),
  __('Typography', 'yaycheckout'),
  __('Spacing & radius', 'yaycheckout'),
  __('Buttons', 'yaycheckout'),
  __('Inputs', 'yaycheckout'),
  __('Custom CSS', 'yaycheckout'),
];

export default function DesignPage() {
  return (
    <PageLayout
      heading={__('Design', 'yaycheckout')}
      description={__('Customize checkout colors, typography and spacing.', 'yaycheckout')}
      actions={<DesignActions />}
    >
      <div className="flex flex-col gap-5 lg:flex-row">
        <div className="flex w-full shrink-0 flex-col gap-3 lg:w-100">
          <ProGate>
            <Card className="gap-3 p-4">
              <div className="flex items-center gap-2">
                <span className="flex-1 text-sm font-semibold">{__('Presets', 'yaycheckout')}</span>
                <ProTag />
              </div>
              <div className="flex flex-wrap gap-1.5">
                {PRESET_NAMES.map((name, index) => (
                  <span
                    key={name}
                    className={
                      0 === index
                        ? 'bg-primary text-primary-foreground border-primary inline-flex h-7 items-center rounded-full border px-3 font-mono text-xs'
                        : 'bg-background inline-flex h-7 items-center rounded-full border px-3 font-mono text-xs'
                    }
                  >
                    {name}
                  </span>
                ))}
              </div>
              <Button type="button" variant="outline" size="sm" className="self-start" disabled>
                <Plus />
                {__('Save current as preset', 'yaycheckout')}
              </Button>
            </Card>
          </ProGate>

          <Card className="gap-0 py-0">
            {GROUP_LABELS.map((label) => (
              <div
                key={label}
                className="flex items-center gap-2 border-b px-4 py-3 text-sm font-semibold last:border-b-0"
              >
                <span className="flex-1">{label}</span>
                <span className="text-muted-foreground font-mono text-xs">+</span>
              </div>
            ))}
          </Card>
        </div>

        <PreviewPane />
      </div>
    </PageLayout>
  );
}
