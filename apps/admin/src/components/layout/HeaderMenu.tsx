import { ComponentProps } from 'react';
import { LayoutTemplate, Plug, Wrench } from 'lucide-react';
import { NavLink } from 'react-router';
import { __ } from '@wordpress/i18n';

import { LAYOUTS_PATH } from '@/lib/helpers/layout.helper';
import { cn } from '@/lib/utils';
import { HeaderNavMenuItem, HeaderNavMenuList } from '@/components/ui/navmenu-header';
import SettingsIcon from '@/components/icons/SettingsIcon';

export function HeaderMenu() {
  return (
    <HeaderNavMenuList className="*:data-[slot=header-navigation-menu-indicator]:bg-primary">
      <CustomMenuItem asChild key="layouts">
        <NavLink to={LAYOUTS_PATH}>
          <LayoutTemplate className="stroke-2" />
          <span>{__('Layouts', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
      <CustomMenuItem asChild key="tools">
        <NavLink to="/tools">
          <Wrench className="stroke-2" />
          <span>{__('Tools', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
      <CustomMenuItem asChild key="settings">
        <NavLink to="/settings">
          <SettingsIcon className="stroke-2" />
          <span>{__('Settings', 'yaycheckout')}</span>
        </NavLink>
      </CustomMenuItem>
      <CustomMenuItem asChild key="compatibility">
        <NavLink to="/compatibility">
          <Plug className="stroke-2" />
          <span>{__('Compatibility', 'yaycheckout')}</span>
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
