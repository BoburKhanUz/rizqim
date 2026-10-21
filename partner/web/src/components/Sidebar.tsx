'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { LogOut } from 'lucide-react';
import { Button, cx } from '@rizqim/ui';
import { sectionOf, type NavItem, type NavKey } from '@/lib/permissions';
import { roleLabel } from '@/lib/labels';
import { LanguageSwitcher } from './LanguageSwitcher';
import { useT } from './I18nProvider';

interface SidebarProps {
  items: NavItem[];
  userName: string;
  role: string;
  // Narrow windows: the sidebar is a drawer, open or closed
  open: boolean;
  onLogout: () => void;
  // A number next to a section (orders waiting for acceptance); 0 or missing: none
  badges?: Partial<Record<NavKey, number>>;
}

export function Sidebar({ items, userName, role, open, onLogout, badges = {} }: SidebarProps) {
  const t = useT();
  const pathname = usePathname();
  return (
    <aside
      className={cx(
        'z-20 flex w-64 shrink-0 flex-col gap-4 border-r border-line bg-surface p-3',
        // Tablet and desktop: a column that stays while the page scrolls. Narrow: a drawer under the top bar.
        'md:sticky md:top-0 md:h-screen md:w-60 lg:w-64',
        open ? 'max-md:fixed max-md:inset-y-0 max-md:left-0 max-md:top-[53px] max-md:overflow-y-auto' : 'max-md:hidden',
      )}
    >
      <div className="hidden px-3 pb-2 pt-3 md:block">
        <p className="text-headline font-bold text-fg">{t('appName')}</p>
        <p className="text-caption text-fg-muted">{t('appTagline')}</p>
      </div>

      <nav aria-label={t('navAria')} className="flex flex-1 flex-col gap-1 overflow-y-auto">
        {items.map((item) => {
          const active = sectionOf(pathname)?.key === item.key;
          const Icon = item.icon;
          const badge = badges[item.key] ?? 0;
          return (
            <Link
              key={item.key}
              href={item.href}
              aria-current={active ? 'page' : undefined}
              className={cx(
                'flex min-h-11 items-center gap-3 rounded-control px-3 text-body font-medium transition-colors',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                active ? 'bg-brand-soft text-brand-soft-fg' : 'text-fg hover:bg-surface-2',
              )}
            >
              <Icon aria-hidden="true" className="size-5 shrink-0" />
              <span className="min-w-0 flex-1 truncate">{t(item.label)}</span>
              {badge > 0 && (
                <span className="inline-flex min-w-6 items-center justify-center rounded-full bg-brand px-1.5 text-caption font-bold text-on-brand">
                  {badge > 99 ? '99+' : badge}
                  <span className="sr-only"> ({t('newOrdersCount', { n: badge })})</span>
                </span>
              )}
            </Link>
          );
        })}
      </nav>

      <div className="flex flex-col gap-3 border-t border-line pt-3">
        <div className="px-3">
          <p className="truncate text-body font-bold text-fg">{userName || t('signedInAs')}</p>
          <p className="text-caption text-fg-muted">{roleLabel(t, role)}</p>
        </div>
        <LanguageSwitcher />
        <Button variant="secondary" icon={LogOut} onClick={onLogout}>
          {t('logout')}
        </Button>
      </div>
    </aside>
  );
}
