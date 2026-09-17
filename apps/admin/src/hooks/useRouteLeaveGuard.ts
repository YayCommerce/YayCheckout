import { useEffect, useState } from 'react';
import { matchPath, useBlocker } from 'react-router';

/** Needs a data router: useBlocker throws under the legacy BrowserRouter. */
export function useRouteLeaveGuard(shouldBlock: boolean, excludedRoutes: string[] = []) {
  const [showDialog, setShowDialog] = useState(false);

  const blocker = useBlocker(
    ({ nextLocation }) =>
      shouldBlock && !excludedRoutes.some((route) => matchPath(route, nextLocation.pathname) !== null),
  );

  useEffect(() => {
    if (blocker.state === 'blocked') {
      setShowDialog(true);
    }
  }, [blocker]);

  useEffect(() => {
    if (!shouldBlock) return;
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      // Older Chromium ignores preventDefault alone.
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [shouldBlock]);

  const confirmLeave = () => {
    setShowDialog(false);
    blocker.proceed?.();
  };

  const cancelLeave = () => {
    setShowDialog(false);
    blocker.reset?.();
  };

  return { showDialog, confirmLeave, cancelLeave };
}
