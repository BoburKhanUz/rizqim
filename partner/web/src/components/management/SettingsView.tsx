'use client';

import { useState } from 'react';
import { CardSkeletons, InlineError, cx } from '@rizqim/ui';
import { useStoreSettings } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { PageHeader } from '../PageHeader';
import { useCan } from './access';
import { DevicesPanel } from './DevicesPanel';
import { errorText } from './errors';
import { ServiceFeesForm } from './ServiceFeesForm';
import { StoreSettingsForm } from './StoreSettingsForm';

type Tab = 'store' | 'fees' | 'devices';

// The store's settings in three tabs: the store (name, timezone, how it takes orders, payment methods), the service charge and
// place fee, and the terminals. Each tab shows when the person's permissions open it (settings.manage, devices.view).
export function SettingsView() {
  const { t } = useI18n();
  const access = useCan();
  const tabs: Tab[] = [];
  if (!access.ready || access.can('settings.manage')) tabs.push('store', 'fees');
  if (!access.ready || access.can('devices.view')) tabs.push('devices');
  const [tab, setTab] = useState<Tab>('store');
  const current = tabs.includes(tab) ? tab : (tabs[0] ?? 'store');

  return (
    <>
      <PageHeader title={t('nav_settings')} subtitle={t('settings_subtitle')} />
      <div role="tablist" aria-label={t('nav_settings')} className="mb-6 flex gap-1 overflow-x-auto border-b border-line">
        {tabs.map((id) => (
          <button
            key={id}
            type="button"
            role="tab"
            id={`settings-tab-${id}`}
            aria-selected={current === id}
            aria-controls={`settings-panel-${id}`}
            onClick={() => setTab(id)}
            className={cx(
              'min-h-11 whitespace-nowrap border-b-2 px-4 text-body font-bold focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand',
              current === id ? 'border-brand text-brand-soft-fg' : 'border-transparent text-fg-muted hover:text-fg',
            )}
          >
            {t(`settings_tab_${id}`)}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`settings-panel-${current}`} aria-labelledby={`settings-tab-${current}`} className="max-w-4xl">
        {current === 'store' && <StoreTab />}
        {current === 'fees' && <ServiceFeesForm />}
        {current === 'devices' && <DevicesPanel />}
      </div>
    </>
  );
}

function StoreTab() {
  const { t } = useI18n();
  const settings = useStoreSettings();
  if (settings.isPending) return <CardSkeletons count={3} lines={3} label={t('loading')} />;
  if (settings.isError || !settings.data) {
    return (
      <InlineError
        title={t('loadErrorTitle')}
        message={settings.isError ? errorText(settings.error, t) : t('mgmt_notFound')}
        retryLabel={t('retry')}
        retrying={settings.isFetching}
        onRetry={() => void settings.refetch()}
      />
    );
  }
  return <StoreSettingsForm settings={settings.data} />;
}

