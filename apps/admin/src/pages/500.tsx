import { useEffect } from 'react';
import { useNavigate, useRouteError } from 'react-router';
import { __ } from '@wordpress/i18n';

import { LAYOUTS_PATH } from '@/lib/helpers/layout.helper';
import { logger } from '@/lib/logger';
import { Button } from '@/components/ui/button';

export default function ErrorPage() {
  const navigate = useNavigate();
  const error = useRouteError() as { message: string };

  useEffect(() => {
    logger.error('route error', error);
  }, [error]);

  return (
    <div className="flex h-[80vh] flex-col items-center justify-center gap-6">
      <div className="text-center">
        <h1 className="text-primary mb-2 text-6xl font-bold">500</h1>
        <h2 className="text-foreground mb-4 text-xl font-semibold">{error?.message || 'Something went wrong'}</h2>
        <p className="text-muted-foreground text-sm">
          {__('Something went wrong. Please try again later.', 'yaycheckout')}
        </p>
      </div>

      <Button variant="primary" onClick={() => navigate(LAYOUTS_PATH)}>
        {__('Back to Layouts', 'yaycheckout')}
      </Button>
    </div>
  );
}
