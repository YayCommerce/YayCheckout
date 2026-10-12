import { Fragment } from 'react';

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';

/** A multi-step dialog's steps, the current one marked. */
export function StepBreadcrumb({ labels, current }: { labels: string[]; current: number }) {
  return (
    <Breadcrumb>
      <BreadcrumbList>
        {labels.map((label, index) => (
          <Fragment key={label}>
            {index > 0 && <BreadcrumbSeparator />}
            <BreadcrumbItem>
              {index === current ? <BreadcrumbPage>{label}</BreadcrumbPage> : <span>{label}</span>}
            </BreadcrumbItem>
          </Fragment>
        ))}
      </BreadcrumbList>
    </Breadcrumb>
  );
}
