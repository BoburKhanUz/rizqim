'use client';

import { useState } from 'react';
import { EmptyState, PageHeader, Tabs } from '@rizqim/ui';
import { ReceiptText } from 'lucide-react';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { FiscalSettingsPanel } from './FiscalSettingsPanel';
import { ReadinessPanel } from './ReadinessPanel';
import { ReceiptsPanel } from './ReceiptsPanel';

type Tab = 'readiness' | 'settings' | 'receipts';

// /fiscal: the MXIK readiness of the menu (menu.view or settings.manage), the store's fiscal settings (settings.manage) and the
// fiscal receipts (finance.view or reports.view). A tab shows only to those who may read what is behind it.
export function FiscalView() {
  const { t } = useI18n();
  const access = useCan();
  const [chosen, setChosen] = useState<Tab | null>(null);

  const available: Tab[] = [];
  if (access.can('menu.view') || access.can('settings.manage')) available.push('readiness');
  if (access.can('settings.manage')) available.push('settings');
  if (access.can('finance.view') || access.can('reports.view')) available.push('receipts');
  const tab = chosen && available.includes(chosen) ? chosen : available[0];

  return (
    <>
      <PageHeader title={t('fiscal_title')} subtitle={t('fiscal_subtitle')} />
      {!access.ready ? null : !tab ? (
        <EmptyState icon={ReceiptText} title={t('mgmt_forbidden')} text={t('fiscal_noTabs')} />
      ) : (
        <>
          <Tabs idPrefix="fiscal" label={t('fiscal_tabs')} value={tab} onChange={setChosen} tabs={available.map((id) => ({ id, label: t(`fiscal_tab_${id}`) }))} />
          <div role="tabpanel" id={`fiscal-panel-${tab}`} aria-labelledby={`fiscal-tab-${tab}`}>
            {tab === 'readiness' && <ReadinessPanel canEditMenu={access.can('menu.manage')} canOpenSettings={access.can('settings.manage')} onOpenSettings={() => setChosen('settings')} />}
            {tab === 'settings' && <FiscalSettingsPanel />}
            {tab === 'receipts' && <ReceiptsPanel canRetry={access.can('settings.manage')} />}
          </div>
        </>
      )}
    </>
  );
}
