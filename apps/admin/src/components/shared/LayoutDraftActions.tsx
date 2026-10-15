import { useNavigate } from 'react-router';
import { __ } from '@wordpress/i18n';

import { editLayoutPath, LAYOUTS_PATH } from '@/lib/helpers/layout.helper';
import { Button } from '@/components/ui/button';

/** "Go to Layouts" and "Open in editor" for a just-created draft; each closes the host dialog first. */
export function LayoutDraftActions({ layoutId, onClose }: { layoutId: number; onClose: () => void }) {
  const navigate = useNavigate();

  function goTo(path: string) {
    onClose();
    navigate(path);
  }

  return (
    <div className="flex gap-2">
      <Button variant="outline" onClick={() => goTo(LAYOUTS_PATH)}>
        {__('Go to layouts', 'yaycheckout')}
      </Button>
      <Button onClick={() => goTo(editLayoutPath(layoutId))}>{__('Open in editor', 'yaycheckout')}</Button>
    </div>
  );
}
