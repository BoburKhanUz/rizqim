'use client';

import { useState, type FormEvent } from 'react';
import { KeyRound, Trash2 } from 'lucide-react';
import { Button, Card, Field, FormError, Notice, Pill, fieldClass } from '@rizqim/ui';
import { useSaveUzQrTillKey, useUzQrSettings, type UzQrTill } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Gate } from '../pos/Gate';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// Wave P: each till's own UzQR API key (the restaurant creates one dynamic QR and one API key per till in uzqr.uz) and the
// store's STIR (edited in the fiscal settings). The key is write-only: the server answers only whether it is set and its
// last four characters, and the field empties after a save. Shown in the QR tab when the store uses the real UzQR driver.

export function UzQrTillsPanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const settings = useUzQrSettings(branchId);
  return (
    <Card className="flex flex-col gap-4">
      <section className="flex flex-col gap-4" aria-labelledby="uzqr-tills-title">
        <div>
          <h2 id="uzqr-tills-title" className="text-headline font-bold text-fg">
            {t('qrp_uzq_title')}
          </h2>
          <p className="text-footnote text-fg-muted">{t('qrp_uzq_intro')}</p>
        </div>
        <Gate query={settings} count={1}>
          {(data) => (
            <>
              <p className="text-footnote font-bold text-fg">{data.tin_set ? t('qrp_uzq_tin', { tin: data.tin ?? '' }) : t('qrp_uzq_tinMissing')}</p>
              {data.tills.length === 0 ? (
                <p className="text-footnote text-fg-muted">{t('qrp_uzq_noTills')}</p>
              ) : (
                <ul className="flex flex-col gap-3">
                  {data.tills.map((till) => (
                    <TillRow key={till.uuid} till={till} />
                  ))}
                </ul>
              )}
            </>
          )}
        </Gate>
      </section>
    </Card>
  );
}

function TillRow({ till }: { till: UzQrTill }) {
  const { t } = useI18n();
  const save = useSaveUzQrTillKey();
  const [key, setKey] = useState('');
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [saved, setSaved] = useState<string | null>(null);

  const send = (patch: { api_key: string } | { remove_api_key: true }, done: string) => {
    setErrors(NO_ERRORS);
    setSaved(null);
    save.mutate(
      { uuid: till.uuid, patch },
      {
        onSuccess: () => {
          setKey('');
          setSaved(done);
        },
        onError: (error) => setErrors(sortServerErrors(error, ['api_key', 'remove_api_key'], t)),
      },
    );
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (key.trim() !== '') send({ api_key: key.trim() }, t('qrp_uzq_saved'));
  };

  return (
    <li className="rounded-control border border-border p-3">
      <form onSubmit={submit} noValidate className="flex flex-col gap-2">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <span className="font-bold text-fg">{till.name ?? t('qrp_uzq_unnamed')}</span>
          <Pill tone={till.has_api_key ? 'success' : 'neutral'}>{till.has_api_key ? t('qrp_uzq_hasKey', { last4: till.api_key_last4 ?? '' }) : t('qrp_uzq_noKey')}</Pill>
        </div>
        {till.message && <p className="text-footnote text-fg-muted">{till.message}</p>}
        <Notice message={saved} />
        <Field label={t('qrp_uzq_key')} hint={t('qrp_uzq_keyHint')} error={errors.fields.api_key} className="sm:max-w-md">
          {(props) => <input {...props} type="password" autoComplete="new-password" spellCheck={false} maxLength={512} value={key} onChange={(event) => setKey(event.target.value)} className={fieldClass} />}
        </Field>
        <FormError message={errors.form ?? errors.fields.remove_api_key ?? null} />
        <div className="flex flex-wrap gap-2">
          <Button type="submit" icon={KeyRound} loading={save.isPending} disabled={key.trim() === ''}>
            {t('qrp_uzq_save')}
          </Button>
          {till.has_api_key && (
            <Button type="button" variant="secondary" icon={Trash2} disabled={save.isPending} onClick={() => send({ remove_api_key: true }, t('qrp_uzq_removed'))}>
              {t('qrp_uzq_remove')}
            </Button>
          )}
        </div>
      </form>
    </li>
  );
}
