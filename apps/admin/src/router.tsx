import { createHashRouter, redirect } from 'react-router';

import { LAYOUTS_PATH } from '@/lib/helpers/layout.helper';
import AppLayout from './AppLayout';
import NotFoundPage from './pages/404';
import ErrorPage from './pages/500';
import CompatibilityPage from './pages/compatibility/CompatibilityPage';
import LayoutsPage from './pages/layouts/LayoutsPage';
import { SETTINGS_SUB_MENUS } from './pages/settings/settings-sub-menus';
import SettingsPage from './pages/settings/SettingsPage';
import ToolsPage from './pages/tools/ToolsPage';

const DEFAULT_SETTINGS_PATH = '/settings/general';

export function getManagerRouter() {
  return createHashRouter([
    {
      path: '/',
      element: <AppLayout />,
      errorElement: <ErrorPage />,
      children: [
        {
          index: true,
          loader: () => redirect(LAYOUTS_PATH),
        },
        {
          path: 'layouts',
          element: <LayoutsPage />,
        },
        {
          path: 'tools',
          element: <ToolsPage />,
        },
        {
          path: 'settings',
          children: [
            {
              index: true,
              loader: () => redirect(DEFAULT_SETTINGS_PATH),
            },
            {
              path: ':subMenu',
              loader: ({ params }) => {
                if (!params.subMenu || !settingSubMenus.includes(params.subMenu)) {
                  return redirect(DEFAULT_SETTINGS_PATH);
                }
              },
              element: <SettingsPage />,
            },
          ],
        },
        {
          path: 'compatibility',
          element: <CompatibilityPage />,
        },
        {
          path: '*',
          element: <NotFoundPage />,
        },
      ],
    },
  ]);
}

const settingSubMenus: readonly string[] = SETTINGS_SUB_MENUS;
