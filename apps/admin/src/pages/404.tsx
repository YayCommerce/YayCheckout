import { useNavigate } from 'react-router';
import { __ } from '@wordpress/i18n';

import { LAYOUTS_PATH } from '@/lib/helpers/layout.helper';
import { Button } from '@/components/ui/button';

export default function NotFoundPage() {
  const navigate = useNavigate();

  return (
    <div className="flex h-[80vh] flex-col items-center justify-center gap-6">
      <div className="text-center">
        <h1 className="text-primary mb-2 text-6xl font-bold">404</h1>
        <h2 className="text-foreground mb-4 text-xl font-semibold">{__('Page not found', 'yaycheckout')}</h2>
        <p className="text-muted-foreground text-sm">
          {__("The page you are looking for doesn't exist or has been moved.", 'yaycheckout')}
        </p>
      </div>

      <Button variant="primary" onClick={() => navigate(LAYOUTS_PATH)}>
        {__('Back to layouts', 'yaycheckout')}
      </Button>
    </div>
  );
}
