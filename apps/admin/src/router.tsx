import { createHashRouter, redirect } from 'react-router';

import AppLayout from './AppLayout';
import NotFoundPage from './pages/404';
import ErrorPage from './pages/500';
import BuilderPage from './pages/builder/BuilderPage';
import DashboardPage from './pages/dashboard/DashboardPage';
import DesignPage from './pages/design/DesignPage';
import FieldsPage from './pages/fields/FieldsPage';
import SettingsPage from './pages/settings/SettingsPage';

export function getManagerRouter() {
  return createHashRouter([
    {
      path: '/',
      element: <AppLayout />,
      errorElement: <ErrorPage />,
      children: [
        {
          index: true,
          loader: () => redirect('/dashboard'),
        },
        {
          path: 'dashboard',
          element: <DashboardPage />,
        },
        {
          path: 'builder',
          element: <BuilderPage />,
        },
        {
          path: 'fields',
          element: <FieldsPage />,
        },
        {
          path: 'design',
          element: <DesignPage />,
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
          path: '*',
          element: <NotFoundPage />,
        },
      ],
    },
  ]);
}

const settingSubMenus = ['general', 'emails', 'advanced'];
