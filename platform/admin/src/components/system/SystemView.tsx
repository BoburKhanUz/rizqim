'use client';

import { useCallback, useId, useState } from 'react';
import Link from 'next/link';
import { ChevronRight, ShieldAlert } from 'lucide-react';
import { Card, cx, EmptyState, PageHeader, TAB_CLASS, tabTone } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { FlagsTab } from './FlagsTab';
import { LocationsTab } from './LocationsTab';
import { PaymentsTab } from './PaymentsTab';
import { SettingsTab } from './SettingsTab';
import { SYSTEM_TABS, type SystemTab } from './tabs';

// The other platform pages that belong to Tizim but live where they are used (docs/PHASE4_PLAN.md 4.9)
const LINKS: ReadonlyArray<{ href: string; label: MessageKey; text: MessageKey; anyOf: readonly string[] }> = [
  { href: '/orders', label: 'system_link_sla', text: 'system_link_slaText', anyOf: ['orders.view'] },
  { href: '/pos?tab=plans', label: 'system_link_plans', text: 'system_link_plansText', anyOf: ['subscriptions.manage', 'finance.view', 'devices.view'] },
  { href: '/team', label: 'nav_team', text: 'system_link_teamText', anyOf: ['team.manage'] },
  { href: '/roles', label: 'nav_roles', text: 'system_link_rolesText', anyOf: ['team.manage'] },
  { href: '/audit', label: 'nav_audit', text: 'system_link_auditText', anyOf: ['audit.view'] },
];

// Tizim (system.manage): platform settings, payment providers, regions and districts, feature flags. The tab is in the address
// (`?tab=payments`), so a link opens where it means to. Every change asks for a reason and a fresh 2FA code.
export function SystemView({ initialTab = 'settings' }: { initialTab?: SystemTab }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('system.manage');
  const [tab, setTab] = useState<SystemTab>(initialTab);
  const prefix = useId();

  const choose = useCallback((next: SystemTab) => {
    setTab(next);
    if (typeof window !== 'undefined') window.history.replaceState(window.history.state, '', next === 'settings' ? '/system' : `/system?tab=${next}`);
  }, []);

  // A link to another tab while this page is open moves the tab
  const [linked, setLinked] = useState(initialTab);
  if (linked !== initialTab) {
    setLinked(initialTab);
    setTab(initialTab);
  }

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_system')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} text={t('system_forbidden')} />
      </>
    );
  }
  if (!allowed) return <PageHeader title={t('nav_system')} />;

  const links = LINKS.filter((link) => can(...link.anyOf));

  return (
    <>
      <PageHeader title={t('nav_system')} subtitle={t('system_subtitle')} />
      <div role="tablist" aria-label={t('system_tabsLabel')} className="mb-4 flex flex-wrap gap-2">
        {SYSTEM_TABS.map((id) => (
          <button
            key={id}
            type="button"
            role="tab"
            id={`${prefix}-tab-${id}`}
            aria-selected={tab === id}
            aria-controls={`${prefix}-panel-${id}`}
            onClick={() => choose(id)}
            className={cx(TAB_CLASS, tabTone(tab === id))}
          >
            {t(`system_tab_${id}`)}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`${prefix}-panel-${tab}`} aria-labelledby={`${prefix}-tab-${tab}`}>
        {tab === 'settings' && <SettingsTab onOpenPayments={() => choose('payments')} />}
        {tab === 'payments' && <PaymentsTab />}
        {tab === 'locations' && <LocationsTab />}
        {tab === 'flags' && <FlagsTab />}
      </div>

      {links.length > 0 && (
        <section aria-labelledby={`${prefix}-links`} className="mt-8 max-w-4xl">
          <h2 id={`${prefix}-links`} className="mb-1 text-headline font-bold text-fg">
            {t('system_links_title')}
          </h2>
          <p className="mb-3 text-footnote text-fg-muted">{t('system_links_text')}</p>
          <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {links.map((link) => (
              <li key={link.href}>
                <Card padded={false}>
                  <Link href={link.href} className="flex min-h-14 items-center gap-3 rounded-card p-3 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    <span className="flex min-w-0 flex-1 flex-col">
                      <span className="text-body font-bold text-fg">{t(link.label)}</span>
                      <span className="text-footnote text-fg-muted">{t(link.text)}</span>
                    </span>
                    <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
                  </Link>
                </Card>
              </li>
            ))}
          </ul>
        </section>
      )}
    </>
  );
}
