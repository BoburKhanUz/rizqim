'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { LogOut } from 'lucide-react';
import { Button, cx } from '@rizqim/ui';
import { roleLabel } from '@/lib/labels';
import { sectionOf, SYSTEM_KEYS, type NavItem } from '@/lib/nav';
import { useT } from './I18nProvider';

interface SidebarProps {
  items: NavItem[];
  userName: string;
  role: string | null;
  // Narrow windows: the sidebar is a drawer, open or closed
  open: boolean;
  onLogout: () => void;
}

// The sections in the order of docs/PHASE4_PLAN.md section 4, then "Tizim" with its four pages. Items the member may not open
// are not in `items` at all.
export function Sidebar({ items, userName, role, open, onLogout }: SidebarProps) {
  const t = useT();
  const pathname = usePathname();
  const active = sectionOf(pathname)?.key;
  const main = items.filter((item) => !SYSTEM_KEYS.includes(item.key));
  const system = items.filter((item) => SYSTEM_KEYS.includes(item.key));

  const link = (item: NavItem) => {
    const Icon = item.icon;
    const current = active === item.key;
    return (
      <li key={item.key}>
        <Link
          href={item.href}
          aria-current={current ? 'page' : undefined}
          className={cx(
            'flex min-h-11 items-center gap-3 rounded-control px-3 text-body font-medium transition-colors',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
            current ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg hover:bg-surface-2',
          )}
        >
          <Icon aria-hidden="true" className="size-5 shrink-0" />
          <span className="min-w-0 flex-1 truncate">{t(item.label)}</span>
        </Link>
      </li>
    );
  };

  return (
    <aside
      className={cx(
        'z-20 flex w-64 shrink-0 flex-col gap-4 border-r border-line bg-surface p-3 print:hidden',
        // Tablet and desktop: a column that stays while the page scrolls. Narrow: a drawer under the top bar.
        'md:sticky md:top-0 md:h-screen md:w-60 lg:w-64',
        open ? 'max-md:fixed max-md:inset-y-0 max-md:left-0 max-md:top-[53px] max-md:overflow-y-auto' : 'max-md:hidden',
      )}
    >
      <div className="hidden px-3 pb-2 pt-3 md:block">
        <p className="text-headline font-bold text-fg">{t('appShortName')}</p>
        <p className="text-caption text-fg-muted">{t('appName')}</p>
      </div>

      <nav aria-label={t('navAria')} className="flex flex-1 flex-col gap-4 overflow-y-auto">
        <ul className="flex flex-col gap-1">{main.map(link)}</ul>
        {system.length > 0 && (
          <div className="flex flex-col gap-1">
            <p id="nav-system-label" className="px-3 text-caption font-bold uppercase tracking-wide text-fg-muted">
              {t('nav_system')}
            </p>
            <ul aria-labelledby="nav-system-label" className="flex flex-col gap-1">
              {system.map(link)}
            </ul>
          </div>
        )}
      </nav>

      <div className="flex flex-col gap-3 border-t border-line pt-3">
        <div className="px-3">
          <p className="truncate text-body font-bold text-fg">{userName || t('signedInAs')}</p>
          <p className="text-caption text-fg-muted">{roleLabel(role)}</p>
        </div>
        <Button variant="secondary" icon={LogOut} onClick={onLogout}>
          {t('logout')}
        </Button>
      </div>
    </aside>
  );
}
