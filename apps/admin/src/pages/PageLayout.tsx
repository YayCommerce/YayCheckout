import { ReactNode } from 'react';

/** Shared page frame: header with an actions slot, then stacked content. */
export default function PageLayout({
  heading,
  description,
  actions,
  children,
}: {
  heading: string;
  description?: string;
  actions?: ReactNode;
  children?: ReactNode;
}) {
  return (
    <div className="mx-auto max-w-7xl p-6">
      <div className="flex items-center justify-between gap-4">
        <div className="space-y-1">
          <h1 className="text-lg font-semibold">{heading}</h1>
          {description ? <p className="text-muted-foreground text-sm">{description}</p> : null}
        </div>
        {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
      </div>

      {children ? <div className="mt-6 space-y-6">{children}</div> : null}
    </div>
  );
}
