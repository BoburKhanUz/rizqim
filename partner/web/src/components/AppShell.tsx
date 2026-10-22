'use client';

import { useEffect, useState, type ReactNode } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { LogOut, Menu, X } from 'lucide-react';
import { Button, IconButton, InlineError } from '@rizqim/ui';
import { canOpen, homePath, sectionOf, visibleNav, type NavItem } from '@/lib/permissions';
import { useAuth } from './AuthProvider';
import { FullPageStatus } from './FullPageStatus';
import { useT } from './I18nProvider';
import { Sidebar } from './Sidebar';
import { BranchScopeProvider } from './shell/BranchScope';
import { LiveOrdersProvider, useLiveOrders } from './shell/LiveOrders';
import { TopBar } from './shell/TopBar';

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

  const section = sectionOf(pathname);
  const permissions = state.status === 'ready' ? state.access.permissions : NO_PERMISSIONS;
  const blocked = state.status === 'ready' && section !== undefined && !canOpen(section, permissions);
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
    <BranchScopeProvider>
      <LiveOrdersProvider>
        <ShellFrame
          nav={nav}
          userName={state.userName}
          role={state.access.role}
          menuOpen={menuOpen}
          onToggleMenu={() => setMenuOpen((open) => !open)}
          onLogout={() => void logout()}
        >
          {blocked ? null : children}
        </ShellFrame>
      </LiveOrdersProvider>
    </BranchScopeProvider>
  );
}

interface ShellFrameProps {
  nav: NavItem[];
  userName: string;
  role: string;
  menuOpen: boolean;
  onToggleMenu: () => void;
  onLogout: () => void;
  children: ReactNode;
}

function ShellFrame({ nav, userName, role, menuOpen, onToggleMenu, onLogout, children }: ShellFrameProps) {
  const t = useT();
  const { newCount } = useLiveOrders();
  return (
    <div className="min-h-screen md:flex">
      {/* Phones and narrow windows: a top bar with the menu in a drawer */}
      <header className="sticky top-0 z-30 flex items-center gap-2 border-b border-line bg-surface px-2 py-1 md:hidden print:hidden">
        <IconButton icon={menuOpen ? X : Menu} label={menuOpen ? t('closeMenu') : t('openMenu')} aria-expanded={menuOpen} onClick={onToggleMenu} />
        <span className="text-headline font-bold text-fg">{t('appName')}</span>
      </header>
      <Sidebar items={nav} userName={userName} role={role} open={menuOpen} onLogout={onLogout} badges={{ orders: newCount }} />
      <main className="min-w-0 flex-1 p-4 md:p-6 lg:p-8 print:p-0">
        <TopBar />
        {children}
      </main>
    </div>
  );
}
