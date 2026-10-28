'use client';

import { useCallback, useId, useState } from 'react';
import { Map as MapIcon, ShieldAlert } from 'lucide-react';
import { cx, EmptyState, PageHeader, TAB_CLASS, tabTone } from '@rizqim/ui';
import { useStuck } from '@/lib/api/integrations';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { ConnectionsTab } from './ConnectionsTab';
import { EventsTab } from './EventsTab';
import { StuckTab } from './StuckTab';
import { INTEGRATIONS_TABS, type IntegrationsTab } from './tabs';

export interface IntegrationsInitial {
  tab?: IntegrationsTab;
  connection?: string;
  event?: string;
}

// Integratsiyalar (integrations.view): the links of stores to outside systems (iiko, Alipos, Yandex Eats, Uzum Tezkor, Partner
// API), the integration journal, the menu map and the orders an outside POS has not confirmed. The adapters come in phase 5;
// until then every list is empty and says so calmly. The tab and the open row are in the address (`?tab=events&event=<uuid>`).
export function IntegrationsView({ initial = {} }: { initial?: IntegrationsInitial }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('integrations.view');
  const [tab, setTab] = useState<IntegrationsTab>(initial.tab ?? (initial.event ? 'events' : 'connections'));
  const [connection, setConnection] = useState<string | null>(initial.connection ?? null);
  const [event, setEvent] = useState<string | null>(initial.event ?? null);
  const prefix = useId();
  const stuck = useStuck(allowed);
  const stuckCount = stuck.data?.count ?? 0;

  const address = useCallback((next: IntegrationsTab, row: string | null) => {
    if (typeof window === 'undefined') return;
    const params = new URLSearchParams();
    if (next !== 'connections') params.set('tab', next);
    if (row && next === 'connections') params.set('connection', row);
    if (row && next === 'events') params.set('event', row);
    const query = params.toString();
    window.history.replaceState(window.history.state, '', query ? `/integrations?${query}` : '/integrations');
  }, []);

  const choose = (next: IntegrationsTab) => {
    setTab(next);
    setConnection(null);
    setEvent(null);
    address(next, null);
  };

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_integrations')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} text={t('integrations_forbidden')} />
      </>
    );
  }
  if (!allowed) return <PageHeader title={t('nav_integrations')} />;

  return (
    <>
      <PageHeader title={t('nav_integrations')} subtitle={t('integrations_subtitle')} />
      <div role="tablist" aria-label={t('integrations_tabsLabel')} className="mb-4 flex flex-wrap gap-2">
        {INTEGRATIONS_TABS.map((id) => (
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
            {id === 'stuck' && stuckCount > 0 ? t('integrations_tab_stuckCount', { count: stuckCount }) : t(`integrations_tab_${id}`)}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`${prefix}-panel-${tab}`} aria-labelledby={`${prefix}-tab-${tab}`}>
        {tab === 'connections' && (
          <ConnectionsTab
            selected={connection}
            onSelect={(uuid) => {
              setConnection(uuid);
              address('connections', uuid);
            }}
            onOpenEvent={(uuid) => {
              setTab('events');
              setEvent(uuid);
              address('events', uuid);
            }}
          />
        )}
        {tab === 'events' && (
          <EventsTab
            selected={event}
            onSelect={(uuid) => {
              setEvent(uuid);
              address('events', uuid);
            }}
          />
        )}
        {tab === 'menu' && <EmptyState icon={MapIcon} title={t('integrations_menu_title')} text={t('integrations_menu_text')} />}
        {tab === 'stuck' && <StuckTab />}
      </div>
    </>
  );
}
