'use client';

import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { ShieldAlert } from 'lucide-react';
import { cx, EmptyState, PageHeader, TAB_CLASS, tabTone } from '@rizqim/ui';
import { ALERT_EVENTS, MONITORING_KEYS, useHealth } from '@/lib/api/monitoring';
import { usePlatformOps } from '@/lib/realtime';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { AlertsTab } from './AlertsTab';
import { FailedJobsTab } from './FailedJobsTab';
import { RulesTab } from './RulesTab';
import { StatusTab } from './StatusTab';
import { MONITORING_TABS, type MonitoringTab } from './tabs';

// Monitoring (monitoring.view): the system's health, the alerts the rules opened, the background jobs that failed and the rules
// themselves. The tab is in the address (`?tab=alerts&alert=12`: "Bugun" links to a particular alert), so a link opens where it
// means to. Every tab asks again every 30 seconds.
export function MonitoringView({ initialTab = 'status', focusAlert = null }: { initialTab?: MonitoringTab; focusAlert?: number | null }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('monitoring.view');
  const [tab, setTab] = useState<MonitoringTab>(initialTab);
  const prefix = useId();
  // The tab strip shows how many alerts are open (the health answer has it; the status tab shares the same query)
  const health = useHealth(allowed);
  const unresolved = (health.data?.alerts.open ?? 0) + (health.data?.alerts.acknowledged ?? 0);

  // Real-time on top of the 30 s polling (which runs anyway, so pollMs 0): an alert event asks again, once per burst. Without
  // Reverb nothing changes: the lists keep polling.
  const client = useQueryClient();
  const pending = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(
    () => () => {
      if (pending.current) clearTimeout(pending.current);
    },
    [],
  );
  usePlatformOps(
    ALERT_EVENTS,
    () => {
      if (pending.current) return;
      pending.current = setTimeout(() => {
        pending.current = null;
        void client.invalidateQueries({ queryKey: MONITORING_KEYS.allAlerts });
        void client.invalidateQueries({ queryKey: MONITORING_KEYS.health });
      }, 400);
    },
    { pollMs: 0, enabled: allowed },
  );

  const choose = useCallback((next: MonitoringTab) => {
    setTab(next);
    // Keep the address in step so the tab can be linked and survives a reload
    if (typeof window !== 'undefined') window.history.replaceState(window.history.state, '', next === 'status' ? '/monitoring' : `/monitoring?tab=${next}`);
  }, []);

  // A link to another tab while this page is open ("Bugun" → /monitoring?tab=alerts) moves the tab
  const [linked, setLinked] = useState(initialTab);
  if (linked !== initialTab) {
    setLinked(initialTab);
    setTab(initialTab);
  }

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_monitoring')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} text={t('monitoring_forbidden')} />
      </>
    );
  }
  if (!allowed) return <PageHeader title={t('nav_monitoring')} />;

  const label = (id: MonitoringTab) => (id === 'alerts' && unresolved > 0 ? t('monitoring_tab_alertsOpen', { count: unresolved }) : t(`monitoring_tab_${id}`));

  return (
    <>
      <PageHeader title={t('nav_monitoring')} subtitle={t('monitoring_subtitle')} />
      <div role="tablist" aria-label={t('monitoring_tabsLabel')} className="mb-4 flex flex-wrap gap-2">
        {MONITORING_TABS.map((id) => (
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
            {label(id)}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`${prefix}-panel-${tab}`} aria-labelledby={`${prefix}-tab-${tab}`}>
        {tab === 'status' && <StatusTab onOpenTab={choose} />}
        {tab === 'alerts' && <AlertsTab focusAlert={focusAlert} />}
        {tab === 'jobs' && <FailedJobsTab />}
        {tab === 'rules' && <RulesTab />}
      </div>
    </>
  );
}
