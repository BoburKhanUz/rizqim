'use client';

import { useState } from 'react';
import { ShieldAlert } from 'lucide-react';
import { EmptyState, Notice, PageHeader, Tabs } from '@rizqim/ui';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { ComplaintsPanel } from './ComplaintsPanel';
import { CUSTOMER_TABS, TAB_LABEL, TAB_PERMISSION, type CustomerTab } from './customersModel';
import { SuspiciousPanel } from './SuspiciousPanel';
import { UsersPanel } from './UsersPanel';

export interface CustomersInitial {
  tab: CustomerTab | null;
  complaint: string | null;
}

// Mijozlar va yordam (P4-31, docs/PHASE4_PLAN.md 4.6): Foydalanuvchilar (users.view), Shikoyatlar and Shubhali holatlar
// (support.view). A tab the member may not open is not shown; Bugun's rows open a complaint (?tab=complaints&complaint=).
export function CustomersView({ initial }: { initial: CustomersInitial }) {
  const t = useT();
  const { ready, can } = useCan();
  const tabs = CUSTOMER_TABS.filter((key) => can(TAB_PERMISSION[key]));
  const [chosen, setChosen] = useState<CustomerTab | null>(initial.tab);
  const [notice, setNotice] = useState<string | null>(null);

  if (ready && tabs.length === 0) {
    return (
      <>
        <PageHeader title={t('customers_title')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const tab: CustomerTab | undefined = chosen && tabs.includes(chosen) ? chosen : tabs[0];
  const choose = (next: CustomerTab) => {
    setChosen(next);
    setNotice(null);
    if (typeof window !== 'undefined') window.history.replaceState(null, '', `/customers?tab=${next}`);
  };

  return (
    <>
      <PageHeader title={t('customers_title')} subtitle={t('customers_subtitle')} />
      <Notice message={notice} />
      {tab && (
        <>
          <Tabs idPrefix="customers" label={t('customers_tabs')} value={tab} onChange={choose} tabs={tabs.map((id) => ({ id, label: t(TAB_LABEL[id]) }))} />
          <div role="tabpanel" id={`customers-panel-${tab}`} aria-labelledby={`customers-tab-${tab}`}>
            {tab === 'users' && <UsersPanel />}
            {tab === 'complaints' && <ComplaintsPanel initialComplaint={initial.tab === 'complaints' || !initial.tab ? initial.complaint : null} canManage={can('support.manage')} onNotice={setNotice} />}
            {tab === 'suspicious' && <SuspiciousPanel />}
          </div>
        </>
      )}
    </>
  );
}
