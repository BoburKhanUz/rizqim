'use client';

import { useState } from 'react';
import { CreditCard, KeyRound } from 'lucide-react';
import { Button, Card, Field, fieldClass, Notice, Pill, Switch } from '@rizqim/ui';
import { clearCredential, replaceCredential, updatePaymentProvider, usePaymentProviders, useProviderAction, type PaymentProviderRow, type ProviderCredential, type ProviderMode } from '@/lib/api/system';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { ListState, ReasonDialog } from './parts';
import { SecretDialog, SecretText, SourceText } from './secrets';

// To'lov tizimlari: the platform's own merchant accounts (Payme, Click, Uzum Bank, Paynet). The switch and the mode are saved with a
// reason; each key is replaced (or cleared, so the server's .env value applies) on its own. Keys never show in full.
export function PaymentsTab() {
  const t = useT();
  const providers = usePaymentProviders();
  const [notice, setNotice] = useState<string | null>(null);

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <Notice message={notice} />
      <p className="text-footnote text-fg-muted">{t('system_pay_intro')}</p>
      <ListState
        loading={!providers.data}
        error={providers.isError ? providers.error : null}
        fetching={providers.isFetching}
        onRetry={() => void providers.refetch()}
        empty={providers.data?.length === 0}
        emptyTitle={t('system_pay_empty')}
        icon={CreditCard}
        errorTitle={t('system_loadError')}
      >
        <ul aria-label={t('system_pay_listLabel')} className="flex flex-col gap-3">
          {providers.data?.map((provider) => (
            <li key={provider.slug}>
              <ProviderCard provider={provider} onDone={setNotice} />
            </li>
          ))}
        </ul>
      </ListState>
    </div>
  );
}

function ProviderCard({ provider, onDone }: { provider: PaymentProviderRow; onDone: (text: string) => void }) {
  const t = useT();
  const [draft, setDraft] = useState({ is_active: provider.is_active, mode: provider.mode });
  const [seen, setSeen] = useState(`${provider.is_active}|${provider.mode}`);
  if (seen !== `${provider.is_active}|${provider.mode}`) {
    setSeen(`${provider.is_active}|${provider.mode}`);
    setDraft({ is_active: provider.is_active, mode: provider.mode });
  }
  const [asking, setAsking] = useState(false);
  const save = useProviderAction(({ change, reason }: { change: { is_active?: boolean; mode?: ProviderMode }; reason: string }) => updatePaymentProvider(provider.slug, change, reason));
  const change: { is_active?: boolean; mode?: ProviderMode } = {};
  if (draft.is_active !== provider.is_active) change.is_active = draft.is_active;
  if (draft.mode !== provider.mode) change.mode = draft.mode;
  const dirty = Object.keys(change).length > 0;

  return (
    <Card>
      <section aria-label={provider.name} className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="w-full min-w-0 break-words text-headline font-bold text-fg sm:w-auto sm:flex-1">{provider.name}</h2>
          <Pill tone={provider.is_active ? 'success' : 'neutral'}>{provider.is_active ? t('system_pay_active') : t('system_pay_inactive')}</Pill>
          <Pill tone={provider.mode === 'test' ? 'warning' : 'info'}>{provider.mode === 'test' ? t('system_pay_modeTest') : t('system_pay_modeProduction')}</Pill>
        </div>
        <p className="text-footnote text-fg-muted">{provider.wired ? t('system_pay_wired') : t('system_pay_notWired')}</p>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="flex items-center justify-between gap-3 rounded-control bg-surface-2 px-3 py-1">
            <span className="text-footnote font-bold text-fg">{t('system_pay_activeLabel')}</span>
            <Switch checked={draft.is_active} label={t('system_pay_activeSwitch', { name: provider.name })} onChange={(isActive) => setDraft((previous) => ({ ...previous, is_active: isActive }))} />
          </div>
          <Field label={t('system_pay_mode')}>
            {(control) => (
              <select {...control} className={fieldClass} value={draft.mode} onChange={(event) => setDraft((previous) => ({ ...previous, mode: event.target.value as ProviderMode }))}>
                <option value="production">{t('system_pay_modeProduction')}</option>
                <option value="test">{t('system_pay_modeTest')}</option>
              </select>
            )}
          </Field>
        </div>
        {dirty && (
          <div className="flex flex-wrap gap-2">
            <Button aria-label={t('system_pay_saveLabel', { name: provider.name })} onClick={() => setAsking(true)}>
              {t('save')}
            </Button>
            <Button variant="ghost" onClick={() => setDraft({ is_active: provider.is_active, mode: provider.mode })}>
              {t('system_revert')}
            </Button>
          </div>
        )}

        <h3 className="mt-1 text-body font-bold text-fg">{t('system_pay_keys')}</h3>
        <ul aria-label={t('system_pay_keysLabel', { name: provider.name })} className="flex flex-col gap-2">
          {provider.credentials.map((credential) => (
            <li key={credential.key}>
              <CredentialRow provider={provider} credential={credential} onDone={onDone} />
            </li>
          ))}
        </ul>
        {provider.updated_at && <p className="text-caption text-fg-muted">{t('system_updatedAt', { time: when(provider.updated_at) })}</p>}
      </section>

      {asking && (
        <ReasonDialog
          title={t('system_pay_reasonTitle', { name: provider.name })}
          submitLabel={t('save')}
          busy={save.isPending}
          error={save.error ? errorText(save.error, t) : null}
          reasonError={fieldError(save.error, 'reason')}
          onClose={() => {
            setAsking(false);
            save.reset();
          }}
          onSubmit={(reason) =>
            save.mutate(
              { change, reason },
              {
                onSuccess: () => {
                  setAsking(false);
                  onDone(t('system_pay_saved', { name: provider.name }));
                },
              },
            )
          }
        />
      )}
    </Card>
  );
}

function CredentialRow({ provider, credential, onDone }: { provider: PaymentProviderRow; credential: ProviderCredential; onDone: (text: string) => void }) {
  const t = useT();
  const [dialog, setDialog] = useState<'replace' | 'clear' | null>(null);
  const replace = useProviderAction(({ value, reason }: { value: string; reason: string }) => replaceCredential(provider.slug, credential.key, value, reason));
  const clear = useProviderAction(({ reason }: { reason: string }) => clearCredential(provider.slug, credential.key, reason));
  const label = `${provider.name}: ${credential.label}`;

  return (
    <div className="flex flex-col gap-2 rounded-control bg-surface-2 p-3 sm:flex-row sm:items-center">
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="flex flex-wrap items-center gap-2 text-body font-bold text-fg">
          <KeyRound aria-hidden="true" className="size-4 text-fg-muted" />
          {credential.label}
          {!credential.known && <Pill tone="neutral">{t('system_pay_unknownKey')}</Pill>}
        </span>
        <span className="text-footnote text-fg">
          <SecretText item={credential} />
        </span>
        <span className="text-caption text-fg-muted">
          <SourceText source={credential.source} />
        </span>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" aria-label={t('system_secret_replaceLabel', { name: label })} onClick={() => setDialog('replace')}>
          {t('system_secret_replace')}
        </Button>
        {credential.source === 'settings' && (
          <Button variant="ghost" aria-label={t('system_secret_clearLabel', { name: label })} onClick={() => setDialog('clear')}>
            {t('system_secret_clear')}
          </Button>
        )}
      </div>
      {dialog === 'replace' && (
        <SecretDialog
          name={label}
          busy={replace.isPending}
          error={replace.error}
          onClose={() => {
            setDialog(null);
            replace.reset();
          }}
          onSubmit={(value, reason) =>
            replace.mutate(
              { value, reason },
              {
                onSuccess: () => {
                  setDialog(null);
                  onDone(t('system_secret_replaced', { name: label }));
                },
              },
            )
          }
        />
      )}
      {dialog === 'clear' && (
        <ReasonDialog
          title={t('system_secret_clearTitle', { name: label })}
          text={t('system_pay_clearText')}
          submitLabel={t('system_secret_clear')}
          tone="danger"
          busy={clear.isPending}
          error={clear.error ? errorText(clear.error, t) : null}
          reasonError={fieldError(clear.error, 'reason')}
          onClose={() => {
            setDialog(null);
            clear.reset();
          }}
          onSubmit={(reason) =>
            clear.mutate(
              { reason },
              {
                onSuccess: () => {
                  setDialog(null);
                  onDone(t('system_secret_cleared', { name: label }));
                },
              },
            )
          }
        />
      )}
    </div>
  );
}
