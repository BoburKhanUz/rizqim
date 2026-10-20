'use client';

import { useEffect, useState, type ReactNode } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { LogOut, Menu, X } from 'lucide-react';
import { Button, IconButton, InlineError } from '@rizqim/ui';
import { homePath, NAV_ITEMS, visibleNav } from '@/lib/permissions';
import { useAuth } from './AuthProvider';
import { FullPageStatus } from './FullPageStatus';
import { useT } from './I18nProvider';
import { Sidebar } from './Sidebar';

const NO_PERMISSIONS: readonly string[] = [];

// The frame of every signed-in page: restores the session, keeps people without a session or without access out, and
// draws the sidebar. The sections a person sees follow the backend's permissions (GET /me/permissions).
export function AppShell({ children }: { children: ReactNode }) {
  const t = useT();
  const router = useRouter();
  const pathname = usePathname();
  const { state, retry, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    if (state.status === 'anonymous') router.replace('/login');
  }, [state.status, router]);

  // A new page closes the drawer
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reacting to navigation
    setMenuOpen(false);
  }, [pathname]);

  const section = NAV_ITEMS.find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
  const permissions = state.status === 'ready' ? state.access.permissions : NO_PERMISSIONS;
  const blocked = state.status === 'ready' && section !== undefined && !permissions.includes(section.permission);
  useEffect(() => {
    // A section the backend would refuse: go to the first one the person may open
    if (blocked) router.replace(homePath(permissions) ?? '/');
  }, [blocked, permissions, router]);

  if (state.status === 'loading' || state.status === 'anonymous') return <FullPageStatus />;

  if (state.status === 'error') {
    return (
      <FullPageStatus>
        <InlineError title={t('loadErrorTitle')} message={t('networkError')} retryLabel={t('retry')} onRetry={retry} className="w-full max-w-md" />
      </FullPageStatus>
    );
  }

  const nav = visibleNav(state.access.permissions);
  if (nav.length === 0) {
    return (
      <FullPageStatus>
        <div role="alert" className="flex max-w-md flex-col items-center gap-4 rounded-card border border-line bg-surface p-8 text-center">
          <h1 className="text-title font-bold text-fg">{t('noAccessTitle')}</h1>
          <p className="text-footnote text-fg-muted">{t('noAccessText')}</p>
          <Button variant="secondary" icon={LogOut} onClick={() => void logout()}>
            {t('logout')}
          </Button>
        </div>
      </FullPageStatus>
    );
  }

  return (
    <div className="min-h-screen md:flex">
      {/* Phones and narrow windows: a top bar with the menu in a drawer */}
      <header className="sticky top-0 z-30 flex items-center gap-2 border-b border-line bg-surface px-2 py-1 md:hidden">
        <IconButton icon={menuOpen ? X : Menu} label={menuOpen ? t('closeMenu') : t('openMenu')} aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)} />
        <span className="text-headline font-bold text-fg">{t('appName')}</span>
      </header>
      <Sidebar items={nav} userName={state.userName} role={state.access.role} open={menuOpen} onLogout={() => void logout()} />
      <main className="min-w-0 flex-1 p-4 md:p-6 lg:p-8">{blocked ? null : children}</main>
    </div>
  );
}
