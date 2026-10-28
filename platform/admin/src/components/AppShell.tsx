'use client';

import { useEffect, useState, type ReactNode } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { LogOut, Menu, Search, X } from 'lucide-react';
import { Button, IconButton, InlineError } from '@rizqim/ui';
import { canOpen, homePath, sectionOf, visibleNav, type NavItem } from '@/lib/nav';
import { useAuth } from './AuthProvider';
import { CommandPalette } from './CommandPalette';
import { FullPageStatus } from './FullPageStatus';
import { useT } from './I18nProvider';
import { IdleGuard } from './IdleGuard';
import { Sidebar } from './Sidebar';

const NO_PERMISSIONS: readonly string[] = [];

// The frame of every signed-in page: restores the session, keeps people without a session or without access out, draws the
// sidebar and the search. The sections a member sees follow the backend's permissions (GET /admin/me).
export function AppShell({ children }: { children: ReactNode }) {
  const t = useT();
  const router = useRouter();
  const pathname = usePathname();
  const { state, retry, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);

  useEffect(() => {
    if (state.status === 'anonymous') router.replace('/login');
  }, [state.status, router]);

  // A new page closes the drawer
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reacting to navigation
    setMenuOpen(false);
  }, [pathname]);

  // Ctrl+K (Cmd+K on a Mac) opens the search from anywhere
  const signedIn = state.status === 'ready';
  useEffect(() => {
    if (!signedIn) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setSearchOpen(true);
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [signedIn]);

  const section = sectionOf(pathname);
  const permissions = state.status === 'ready' ? state.me.permissions : NO_PERMISSIONS;
  const blocked = state.status === 'ready' && section !== undefined && !canOpen(section, permissions);
  useEffect(() => {
    // A section the backend would refuse: go to the first one the member may open
    if (blocked) router.replace(homePath(permissions));
  }, [blocked, permissions, router]);

  if (state.status === 'loading' || state.status === 'anonymous') return <FullPageStatus />;

  if (state.status === 'error') {
    return (
      <FullPageStatus>
        <InlineError title={t('loadErrorTitle')} message={t('networkError')} retryLabel={t('retry')} onRetry={retry} className="w-full max-w-md" />
      </FullPageStatus>
    );
  }

  const nav = visibleNav(state.me.permissions);
  return (
    <>
      <ShellFrame
        nav={nav}
        userName={state.me.member.name}
        role={state.me.platform_role}
        menuOpen={menuOpen}
        onToggleMenu={() => setMenuOpen((open) => !open)}
        onSearch={() => setSearchOpen(true)}
        onLogout={() => void logout()}
      >
        {blocked ? null : children}
      </ShellFrame>
      {searchOpen && <CommandPalette onClose={() => setSearchOpen(false)} />}
      <IdleGuard />
    </>
  );
}

interface ShellFrameProps {
  nav: NavItem[];
  userName: string;
  role: string | null;
  menuOpen: boolean;
  onToggleMenu: () => void;
  onSearch: () => void;
  onLogout: () => void;
  children: ReactNode;
}

function ShellFrame({ nav, userName, role, menuOpen, onToggleMenu, onSearch, onLogout, children }: ShellFrameProps) {
  const t = useT();
  return (
    <div className="min-h-screen md:flex">
      <a href="#content" className="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-control focus:bg-surface focus:px-3 focus:py-2 focus:text-body focus:text-fg">
        {t('skipToContent')}
      </a>
      {/* Phones and narrow windows: a top bar with the menu in a drawer */}
      <header className="sticky top-0 z-30 flex items-center gap-2 border-b border-line bg-surface px-2 py-1 md:hidden print:hidden">
        <IconButton icon={menuOpen ? X : Menu} label={menuOpen ? t('closeMenu') : t('openMenu')} aria-expanded={menuOpen} onClick={onToggleMenu} />
        <span className="min-w-0 flex-1 truncate text-headline font-bold text-fg">{t('appShortName')}</span>
        <IconButton icon={Search} label={t('search_button')} onClick={onSearch} />
      </header>
      <Sidebar items={nav} userName={userName} role={role} open={menuOpen} onLogout={onLogout} />
      <main id="content" className="min-w-0 flex-1 p-4 md:p-6 lg:p-8 print:p-0">
        {nav.every((item) => item.anyOf.length === 0) && <NoAccessBanner onLogout={onLogout} />}
        <div className="mb-4 hidden items-center justify-end border-b border-line pb-3 md:flex md:mb-6 print:hidden">
          <button
            type="button"
            onClick={onSearch}
            className="inline-flex min-h-11 w-full max-w-xs items-center gap-2 rounded-control border border-line bg-surface px-3 text-body text-fg-muted hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            <Search aria-hidden="true" className="size-4 shrink-0" />
            <span className="flex-1 text-left">{t('search_button')}</span>
            <kbd aria-hidden="true" className="rounded-control border border-line px-1.5 py-0.5 text-caption">
              {t('search_buttonHint')}
            </kbd>
          </button>
        </div>
        {children}
      </main>
    </div>
  );
}

// A member whose role holds no section yet still has "Sessiyalarim" (it needs no permission): this explains why the menu is that short
function NoAccessBanner({ onLogout }: { onLogout: () => void }) {
  const t = useT();
  return (
    <div role="status" className="mb-6 flex flex-col items-start gap-3 rounded-card border border-line bg-surface p-4">
      <h2 className="text-headline font-bold text-fg">{t('noAccessTitle')}</h2>
      <p className="text-footnote text-fg-muted">{t('noAccessText')}</p>
      <Button variant="secondary" icon={LogOut} onClick={onLogout}>
        {t('logout')}
      </Button>
    </div>
  );
}
