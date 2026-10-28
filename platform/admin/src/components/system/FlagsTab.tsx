'use client';

import { useEffect, useState } from 'react';
import { Flag, Search } from 'lucide-react';
import { Button, Card, cx, Field, fieldClass, InlineError, Notice, Pill, Switch, CardSkeletons } from '@rizqim/ui';
import { resetFlags, setFlag, useFlagAction, useFlags, useFlagStores, type FeatureFlag, type FlagStoreRow } from '@/lib/api/system';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { ListState, Pager, ReasonDialog } from './parts';

// Funksiyalar: the feature flags declared in the backend's code (App\Features\Features), switched per store the way the old
// superadmin page did. A store without its own value follows the flag's default; "Standartga qaytarish" forgets its own values.
// Every switch asks for a reason.

type Pending = { kind: 'set'; store: FlagStoreRow; flag: FeatureFlag; active: boolean } | { kind: 'reset'; store: FlagStoreRow };

export function FlagsTab() {
  const t = useT();
  const flags = useFlags();
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  useEffect(() => {
    const timer = setTimeout(() => {
      setQ(search);
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);
  const stores = useFlagStores(q, page, (flags.data?.length ?? 0) > 0);
  const [pending, setPending] = useState<Pending | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const action = useFlagAction((input: { pending: Pending; reason: string }) =>
    input.pending.kind === 'set' ? setFlag(input.pending.flag.name, input.pending.store.store.id, input.pending.active, input.reason) : resetFlags(input.pending.store.store.id, input.reason),
  );

  if (flags.isError) return <InlineError title={t('system_loadError')} message={errorText(flags.error, t)} retryLabel={t('retry')} retrying={flags.isFetching} onRetry={() => void flags.refetch()} />;
  if (!flags.data) return <CardSkeletons count={2} lines={3} label={t('loading')} />;
  const list = flags.data;

  return (
    <div className="flex max-w-5xl flex-col gap-4">
      <Notice message={notice} />
      <p className="text-footnote text-fg-muted">{t('system_flag_intro')}</p>
      <ListState loading={false} error={null} fetching={false} onRetry={() => undefined} empty={list.length === 0} emptyTitle={t('system_flag_empty')} emptyText={t('system_flag_emptyText')} icon={Flag} errorTitle={t('system_loadError')}>
        <ul aria-label={t('system_flag_listLabel')} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {list.map((flag) => (
            <li key={flag.name}>
              <Card className="flex h-full flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="min-w-0 flex-1 break-words text-body font-bold text-fg">{flag.label}</h2>
                  <Pill tone={flag.default ? 'success' : 'neutral'}>{flag.default ? t('system_flag_defaultOn') : t('system_flag_defaultOff')}</Pill>
                </div>
                <p className="text-footnote text-fg-muted">{flag.description}</p>
                <p className="font-mono text-caption text-fg-muted">{flag.name}</p>
                <p className="text-footnote text-fg">{t('system_flag_counts', { on: flag.stores_on, off: flag.stores_off, total: flag.stores_total })}</p>
              </Card>
            </li>
          ))}
        </ul>

        <h2 className="mt-2 text-headline font-bold text-fg">{t('system_flag_storesTitle')}</h2>
        <Field label={t('system_flag_search')}>
          {(control) => (
            <div className="relative sm:max-w-sm">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} placeholder={t('system_flag_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
        <ListState
          loading={!stores.data}
          error={stores.isError ? stores.error : null}
          fetching={stores.isFetching}
          onRetry={() => void stores.refetch()}
          empty={stores.data?.data.length === 0}
          emptyTitle={t('system_flag_noStores')}
          icon={Flag}
          errorTitle={t('system_loadError')}
        >
          <ul aria-label={t('system_flag_storesLabel')} className="flex flex-col gap-2">
            {stores.data?.data.map((row) => (
              <li key={row.store.id}>
                <Card className="flex flex-col gap-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="min-w-0 flex-1 break-words text-body font-bold text-fg">{row.store.name}</span>
                    {Object.values(row.flags).some((value) => value.stored) && (
                      <Button variant="ghost" aria-label={t('system_flag_resetLabel', { name: row.store.name })} onClick={() => setPending({ kind: 'reset', store: row })}>
                        {t('system_flag_reset')}
                      </Button>
                    )}
                  </div>
                  <ul className="flex flex-col gap-1">
                    {list.map((flag) => {
                      const value = row.flags[flag.name] ?? { active: flag.default, stored: false };
                      return (
                        <li key={flag.name} className="flex flex-wrap items-center gap-2 rounded-control bg-surface-2 px-3 py-1">
                          <span className="min-w-0 flex-1 text-footnote text-fg">{flag.label}</span>
                          <Pill tone={value.stored ? 'info' : 'neutral'}>{value.stored ? t('system_flag_own') : t('system_flag_followsDefault')}</Pill>
                          <Switch checked={value.active} label={t('system_flag_switch', { flag: flag.label, store: row.store.name })} onChange={(active) => setPending({ kind: 'set', store: row, flag, active })} />
                        </li>
                      );
                    })}
                  </ul>
                </Card>
              </li>
            ))}
          </ul>
          {stores.data && <Pager page={stores.data.current_page} lastPage={stores.data.last_page} onPage={setPage} />}
        </ListState>
      </ListState>

      {pending && (
        <ReasonDialog
          title={pending.kind === 'set' ? t(pending.active ? 'system_flag_onTitle' : 'system_flag_offTitle', { flag: pending.flag.label, store: pending.store.store.name }) : t('system_flag_resetTitle', { name: pending.store.store.name })}
          text={pending.kind === 'reset' ? t('system_flag_resetText') : undefined}
          submitLabel={pending.kind === 'set' ? (pending.active ? t('system_flag_on') : t('system_flag_off')) : t('system_flag_reset')}
          busy={action.isPending}
          error={action.error ? errorText(action.error, t) : null}
          reasonError={fieldError(action.error, 'reason')}
          onClose={() => {
            setPending(null);
            action.reset();
          }}
          onSubmit={(reason) =>
            action.mutate(
              { pending, reason },
              {
                onSuccess: () => {
                  setNotice(pending.kind === 'set' ? t(pending.active ? 'system_flag_turnedOn' : 'system_flag_turnedOff', { flag: pending.flag.label, store: pending.store.store.name }) : t('system_flag_resetDone', { name: pending.store.store.name }));
                  setPending(null);
                },
              },
            )
          }
        />
      )}
    </div>
  );
}
