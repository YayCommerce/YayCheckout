import { DragEvent, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ImportModal } from '../import-modal/ImportModal';

type ImportResult = { status: 'idle' } | { status: 'success'; message: string } | { status: 'error'; message: string };

export function ImportCard() {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragging, setDragging] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<ImportResult>({ status: 'idle' });
  const [modalOpen, setModalOpen] = useState(false);

  const pick = (picked: File | undefined) => {
    if (!picked) return;
    setFile(picked);
    if (!picked.name.toLowerCase().endsWith('.json')) {
      setResult({ status: 'error', message: __('Only .json files can be imported.', 'yaycheckout') });
      return;
    }
    setResult({ status: 'idle' });
    setModalOpen(true);
  };

  const onDrop = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault();
    setDragging(false);
    pick(event.dataTransfer.files[0]);
  };

  return (
    <Card className="bg-muted/30">
      <CardHeader>
        <CardTitle>{__('Import', 'yaycheckout')}</CardTitle>
        <CardDescription>{__('Creates a draft. Never touches the live layout.', 'yaycheckout')}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        <div
          role="button"
          tabIndex={0}
          onClick={() => inputRef.current?.click()}
          onKeyDown={(event) => event.key === 'Enter' && inputRef.current?.click()}
          onDragOver={(event) => {
            event.preventDefault();
            setDragging(true);
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={onDrop}
          className={cn(
            'flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed px-4 py-6 text-center',
            dragging ? 'border-primary bg-primary/5' : 'bg-muted/30',
          )}
        >
          <span className="text-sm">
            {file
              ? sprintf(
                  /* translators: %s: file name */
                  __('Selected: %s', 'yaycheckout'),
                  file.name,
                )
              : __('Drop a .json file here', 'yaycheckout')}
          </span>
          <span className="text-muted-foreground text-xs">{__('or click to browse', 'yaycheckout')}</span>
          <input
            ref={inputRef}
            type="file"
            accept=".json,application/json"
            className="hidden"
            onChange={(event) => pick(event.target.files?.[0])}
          />
        </div>

        <div
          className={cn(
            'rounded-md border px-4 py-3 text-xs',
            result.status === 'idle' && 'bg-muted/30 text-muted-foreground',
            result.status === 'success' && 'border-green-600/30 bg-green-50 text-green-700',
            result.status === 'error' && 'border-destructive/30 bg-destructive/5 text-destructive',
          )}
        >
          {result.status === 'idle' ? __('validation result appears here', 'yaycheckout') : result.message}
        </div>
      </CardContent>
      <ImportModal open={modalOpen} onOpenChange={setModalOpen} />
    </Card>
  );
}
