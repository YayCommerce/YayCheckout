import { ReactNode } from 'react';

/** `warning` is for the rare setting that can break a working store. */
export function SettingRow({
  title,
  description,
  warning,
  children,
}: {
  title: string;
  description?: ReactNode;
  warning?: string;
  children?: ReactNode;
}) {
  return (
    <div className="flex items-start gap-4 border-b p-4 last:border-b-0">
      <div className="flex flex-1 flex-col gap-1">
        <div className="text-sm font-semibold">{title}</div>
        {description ? <div className="text-muted-foreground text-xs/relaxed">{description}</div> : null}
        {warning ? <div className="text-xs/relaxed text-amber-700">{warning}</div> : null}
      </div>
      {children ? <div className="flex shrink-0 items-center">{children}</div> : null}
    </div>
  );
}
