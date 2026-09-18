import { createHashRouter, redirect } from 'react-router';

import AppLayout from './AppLayout';
import NotFoundPage from './pages/404';
import ErrorPage from './pages/500';
import CompatibilityPage from './pages/compatibility/CompatibilityPage';
import DesignPage from './pages/design/DesignPage';
import LayoutsPage from './pages/layouts/LayoutsPage';
import { SETTINGS_SUB_MENUS } from './pages/settings/settings-sub-menus';
import SettingsPage from './pages/settings/SettingsPage';
import ToolsPage from './pages/tools/ToolsPage';

export function getManagerRouter() {
  return createHashRouter([
    {
      path: '/',
      element: <AppLayout />,
      errorElement: <ErrorPage />,
      children: [
        {
          index: true,
          loader: () => redirect('/layouts'),
        },
        {
          path: 'layouts',
          element: <LayoutsPage />,
        },
        {
          path: 'design',
          element: <DesignPage />,
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
              loader: () => redirect('/settings/general'),
            },
            {
              path: ':subMenu',
              loader: ({ params }) => {
                if (!params.subMenu || !settingSubMenus.includes(params.subMenu)) {
                  return redirect('/settings/general');
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
