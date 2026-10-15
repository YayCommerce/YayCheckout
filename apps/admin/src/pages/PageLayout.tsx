import { ReactNode } from 'react';

/** Shared page frame: title, description and an actions slot, then stacked content. */
export default function PageLayout({
  title,
  description,
  actions,
  children,
}: {
  title: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
  children?: ReactNode;
}) {
  return (
    <div className="mx-auto max-w-7xl p-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
          {description ? <div className="text-muted-foreground text-sm">{description}</div> : null}
        </div>
        {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
      </div>

      {children ? <div className="mt-6 space-y-6">{children}</div> : null}
    </div>
  );
}
