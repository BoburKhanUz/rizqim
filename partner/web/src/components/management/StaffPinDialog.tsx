'use client';

import { useState, type FormEvent } from 'react';
import { KeyRound } from 'lucide-react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import { getApiFieldError, useClearManagedStaffPin, useSetManagedStaffPin, type StaffMember } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';

// PUT /owner/staff/{id}/pin sets (or replaces) the 4 to 6 digit PIN a terminal login uses; DELETE clears it. The backend
// refuses PINs that are too easy (0000, 1234 ...) with a message in Uzbek, shown under the field. The first PIN gives the
// member a four digit staff code, shown after saving.
export function StaffPinDialog({ member, onClose, onChanged }: { member: StaffMember; onClose: () => void; onChanged: (message: string) => void }) {
  const { t } = useI18n();
  const [pin, setPin] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [pinError, setPinError] = useState<string | undefined>();
  const [confirmClear, setConfirmClear] = useState(false);
  const [saved, setSaved] = useState<{ code: string | null } | null>(null);
  const set = useSetManagedStaffPin();
  const clear = useClearManagedStaffPin();
  const busy = set.isPending || clear.isPending;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    setPinError(undefined);
    if (!/^\d{4,6}$/.test(pin)) {
      setPinError(t('staff_pinInvalid'));
      return;
    }
    set.mutate(
      { id: member.id, pin },
      {
        onSuccess: (result) => {
          setPin('');
          setSaved({ code: result.staff_code ?? member.staff_code });
        },
        onError: (err) => {
          const field = getApiFieldError(err, 'pin');
          if (field) setPinError(field);
          else setError(errorText(err, t));
        },
      },
    );
  };

  const clearPin = () => {
    setError(null);
    clear.mutate(member.id, { onSuccess: () => onChanged(t('staff_pinCleared', { name: member.name })), onError: (err) => setError(errorText(err, t)) });
  };

  if (saved) {
    return (
      <Dialog
        open
        variant="center"
        title={t('staff_pinSaved')}
        description={t('staff_pinSavedText', { name: member.name })}
        closeLabel={t('mgmt_close')}
        onClose={() => onChanged(t('staff_pinSavedNotice', { name: member.name }))}
        footer={
          <Button onClick={() => onChanged(t('staff_pinSavedNotice', { name: member.name }))} data-autofocus>
            {t('mgmt_done')}
          </Button>
        }
      >
        {saved.code && (
          <div className="rounded-card bg-surface-2 p-4 text-center">
            <p className="text-footnote text-fg-muted">{t('staff_code')}</p>
            <p className="text-title font-bold tracking-widest text-fg">{saved.code}</p>
          </div>
        )}
      </Dialog>
    );
  }

  return (
    <Dialog open title={t('staff_pinTitle', { name: member.name })} description={member.has_pin ? t('staff_pinHas') : t('staff_pinNone')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        {member.has_pin && member.staff_code && (
          <p className="text-footnote text-fg-muted">
            {t('staff_code')}: <span className="font-bold text-fg">{member.staff_code}</span>
          </p>
        )}
        <Field label={member.has_pin ? t('staff_newPin') : t('staff_pin')} hint={t('staff_pinHint')} error={pinError}>
          {(props) => (
            <input
              {...props}
              data-autofocus
              type="password"
              inputMode="numeric"
              autoComplete="new-password"
              maxLength={6}
              value={pin}
              onChange={(event) => setPin(event.target.value.replace(/\D/g, '').slice(0, 6))}
              className={fieldClass}
            />
          )}
        </Field>
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" icon={KeyRound} loading={set.isPending} disabled={clear.isPending}>
            {member.has_pin ? t('staff_changePin') : t('staff_setPin')}
          </Button>
        </div>
        {member.has_pin && (
          <div className="border-t border-line pt-3">
            {confirmClear ? (
              <div className="flex flex-col gap-2">
                <p className="text-footnote text-fg-muted">{t('staff_clearPinText')}</p>
                <div className="flex gap-2 [&>*]:flex-1">
                  <Button variant="secondary" onClick={() => setConfirmClear(false)} disabled={busy}>
                    {t('mgmt_cancel')}
                  </Button>
                  <Button variant="danger" onClick={clearPin} loading={clear.isPending} disabled={set.isPending}>
                    {t('staff_clearPinConfirm')}
                  </Button>
                </div>
              </div>
            ) : (
              <Button variant="ghost" onClick={() => setConfirmClear(true)} disabled={busy} className="text-danger-soft-fg">
                {t('staff_clearPin')}
              </Button>
            )}
          </div>
        )}
      </form>
    </Dialog>
  );
}
