import { ComponentProps } from 'react';
import { LayoutTemplate, ListChecks, Paintbrush } from 'lucide-react';
import { NavLink } from 'react-router';
import { __ } from '@wordpress/i18n';

import { cn } from '@/lib/utils';
import { HeaderNavMenuItem, HeaderNavMenuList } from '@/components/ui/navmenu-header';
import DashboardIcon from '@/components/icons/DashboardIcon';
import SettingsIcon from '@/components/icons/SettingsIcon';

export function HeaderMenu() {
  return (
    <HeaderNavMenuList className="*:data-[slot=header-navigation-menu-indicator]:bg-primary">
      <CustomMenuItem asChild key="dashboard">
        <NavLink to="/dashboard">
          <DashboardIcon className="stroke-2" />
          <span>{__('Dashboard', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
      <CustomMenuItem asChild key="builder">
        <NavLink to="/builder">
          <LayoutTemplate className="stroke-2" />
          <span>{__('Builder', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
      <CustomMenuItem asChild key="fields">
        <NavLink to="/fields">
          <ListChecks className="stroke-2" />
          <span>{__('Fields', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
      <CustomMenuItem asChild key="design">
        <NavLink to="/design">
          <Paintbrush className="stroke-2" />
          <span>{__('Design', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
      <CustomMenuItem asChild key="settings">
        <NavLink to="/settings">
          <SettingsIcon className="stroke-2" />
          <span>{__('Settings', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
    </HeaderNavMenuList>
  );
}

function CustomMenuItem({ className, ...props }: ComponentProps<typeof HeaderNavMenuItem>) {
  return (
    <HeaderNavMenuItem
      className={cn(
        'max-md:flex-col sm:max-md:px-3',
        'sm:max-md:pt-2',
        '*:[svg]:size-4 sm:*:[svg]:size-3.5 md:*:[svg]:size-4',
        'max-md:text-xs max-sm:*:[span]:hidden',
        'aria-[current=page]:text-primary',
        className,
      )}
      {...props}
    />
  );
}
