'use client';

import { useState } from 'react';
import { Card, FormError, Notice } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';
import { SelectField } from '../pos/fields';
import type { ExpoScope } from './api';
import { useKdsTargets, useSaveExpoScope } from './hooks';

// Which orders go through the expo (assembly) station before their number is called (owner decision 03.11.2026, module
// kds_expo): every order, one station or more (the default), or only an order cooked on two or more stations. Saved as soon
// as it is picked; a payment never waits for the expo.
export function ExpoScopePanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const targets = useKdsTargets(branchId);
  const save = useSaveExpoScope(branchId);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  return (
    <Gate query={targets} count={1}>
      {(page) => {
        const hasExpo = page.stations.some((station) => station.kind === 'expo' && station.is_active);
        const change = (scope: ExpoScope) => {
          setNotice(null);
          setError(null);
          save.mutate(scope, {
            onSuccess: () => setNotice(t(scope === 'all' ? 'kds_expo_savedAll' : 'kds_expo_savedMulti')),
            onError: (failure) => setError(errorText(failure, t)),
          });
        };

        return (
          <Card className="flex flex-col gap-3">
            <div>
              <h2 className="text-headline font-bold text-fg">{t('kds_expo_title')}</h2>
              <p className="text-footnote text-fg-muted">{t('kds_expo_intro')}</p>
            </div>
            <Notice message={notice} />
            <FormError message={error} />
            <SelectField<ExpoScope>
              label={t('kds_expo_scope')}
              value={page.expo_scope ?? 'all'}
              onChange={change}
              disabled={save.isPending}
              className="sm:max-w-md"
              hint={hasExpo ? undefined : t('kds_expo_noStation')}
              options={[
                { value: 'all', label: t('kds_expo_all') },
                { value: 'multi_station', label: t('kds_expo_multi') },
              ]}
            />
          </Card>
        );
      }}
    </Gate>
  );
}
