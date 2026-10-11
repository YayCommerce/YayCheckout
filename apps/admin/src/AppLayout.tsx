import { Outlet, useLocation, useNavigate } from 'react-router';

import { LAYOUTS_PATH } from '@/lib/helpers/layout.helper';
import { Toaster } from '@/components/ui/sonner';
import Footer from '@/components/layout/Footer';
import Header from '@/components/layout/Header';
import { SetupWizard } from '@/components/setup-wizard/SetupWizard';

export default function AppLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const pathSegments = location.pathname.split('/').filter(Boolean);
  return (
    <div>
      <Header />

      <main>
        <Toaster />
        <Outlet />
        <SetupWizard />
      </main>

      <Footer
        currentMenu={pathSegments.length ? pathSegments : ['layouts']}
        onBackToDefault={() => navigate(LAYOUTS_PATH)}
      />
    </div>
  );
}
